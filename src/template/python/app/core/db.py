"""
Query builder and connection for MySQL/MariaDB or PostgreSQL.

Queries are built MySQL-style (? placeholders, backtick identifiers), the same
SQL as every adapter of the framework builds for the same chain
(spec/query_cases.json); for PostgreSQL, backticks become double quotes, and
for both drivers ? becomes %s just before the query runs.

Configuration (environment or .env): DBDRIVER (postgres, the default for this
adapter, or mysql), DBHOST, DBPORT, DBUSER, DBPWD, DBNAME. The older
POSTGRES_SERVER / POSTGRES_DB / POSTGRES_USER / POSTGRES_PASSWORD /
POSTGRES_PORT names still work.
"""
import decimal
import os
import re
from typing import Any, Dict, List, Optional, Tuple

from pydantic_settings import BaseSettings, SettingsConfigDict
from sqlalchemy import create_engine


class DatabaseSettings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")

    dbdriver: str = "postgres"
    dbhost: Optional[str] = None
    dbport: Optional[int] = None
    dbuser: Optional[str] = None
    dbpwd: Optional[str] = None
    dbname: Optional[str] = None
    # older names
    postgres_server: str = "localhost"
    postgres_db: str = "app"
    postgres_user: str = "postgres"
    postgres_password: str = "postgres"
    postgres_port: int = 5432

    @property
    def driver(self) -> str:
        d = self.dbdriver.strip().lower()
        if d in ("", "postgres", "postgresql", "pg"):
            return "postgres"
        if d in ("mysql", "mariadb"):
            return "mysql"
        raise ValueError(f'Unknown DBDRIVER "{self.dbdriver}". Use postgres or mysql.')


_db_settings: Optional[DatabaseSettings] = None


def get_db_settings() -> DatabaseSettings:
    global _db_settings
    if _db_settings is None:
        _db_settings = DatabaseSettings()
    return _db_settings


# ── Connection pool ──────────────────────────────────────────────────────────
# SQLAlchemy is used only as a connection pool under this DB/Model API (no
# SQLAlchemy models). Size per worker with DB_POOL_SIZE / DB_MAX_OVERFLOW.
_pool_engine = None


def _pool():
    global _pool_engine
    if _pool_engine is None:
        s = get_db_settings()
        if s.driver == "postgres":
            import psycopg

            def _connect():
                return psycopg.connect(
                    host=s.dbhost or s.postgres_server,
                    dbname=s.dbname or s.postgres_db,
                    user=s.dbuser or s.postgres_user,
                    password=s.dbpwd if s.dbpwd is not None else s.postgres_password,
                    port=s.dbport or s.postgres_port,
                )

            url = "postgresql+psycopg://"
        else:
            import pymysql

            def _connect():
                return pymysql.connect(
                    host=s.dbhost or "localhost",
                    port=s.dbport or 3306,
                    user=s.dbuser or "root",
                    password=s.dbpwd or "",
                    database=s.dbname,
                    charset="utf8mb4",
                )

            url = "mysql+pymysql://"
        _pool_engine = create_engine(
            url,
            creator=_connect,
            pool_size=int(os.getenv("DB_POOL_SIZE", "2")),
            max_overflow=int(os.getenv("DB_MAX_OVERFLOW", "2")),
            pool_timeout=30,
            pool_pre_ping=True,
            pool_recycle=1800,
        )
    return _pool_engine


def close() -> None:
    """Close the pool (tests, scripts)."""
    global _pool_engine
    if _pool_engine is not None:
        _pool_engine.dispose()
        _pool_engine = None


def ident(name: str) -> str:
    """Quote one identifier, MySQL-style (rewritten for PostgreSQL when run)."""
    return "`" + name.replace("`", "``") + "`"


_DOLLAR = re.compile(r"\$([A-Za-z_][A-Za-z0-9_]*)?\$")


def driver_sql(sql: str, postgres: bool) -> str:
    """
    MySQL-style SQL for the Python drivers: ? becomes %s and a literal % becomes
    %% (both drivers read % as a placeholder anywhere); for PostgreSQL,
    backtick identifiers become double-quoted. ? inside strings, quoted
    identifiers, dollar quotes and comments is left as it is.
    """
    out: List[str] = []
    i, n = 0, len(sql)

    def copy(text: str) -> None:
        out.append(text.replace("%", "%%"))

    while i < n:
        c = sql[i]
        if c in ("'", '"'):
            j = i + 1
            while j < n:
                if sql[j] == c:
                    if j + 1 < n and sql[j + 1] == c:
                        j += 2
                        continue
                    break
                if c == "'" and sql[j] == "\\":
                    j += 1
                j += 1
            copy(sql[i:j + 1])
            i = j + 1
        elif c == "`":
            j, ident_ = i + 1, []
            while j < n:
                if sql[j] == "`":
                    if j + 1 < n and sql[j + 1] == "`":
                        ident_.append("`")
                        j += 2
                        continue
                    break
                ident_.append(sql[j])
                j += 1
            name = "".join(ident_)
            copy('"' + name.replace('"', '""') + '"' if postgres else "`" + name.replace("`", "``") + "`")
            i = j + 1
        elif sql.startswith("--", i):
            end = sql.find("\n", i)
            end = n if end < 0 else end
            copy(sql[i:end])
            i = end
        elif sql.startswith("/*", i):
            end = sql.find("*/", i + 2)
            end = n if end < 0 else end + 2
            copy(sql[i:end])
            i = end
        elif c == "$" and _DOLLAR.match(sql, i):
            tag = _DOLLAR.match(sql, i).group(0)
            end = sql.find(tag, i + len(tag))
            end = n if end < 0 else end + len(tag)
            copy(sql[i:end])
            i = end
        elif c == "?":
            out.append("%s")
            i += 1
        else:
            copy(c)
            i += 1
    return "".join(out)


RAW = object()  # marks a where entry holding ORM-built SQL (where_has)


def _plain(value: Any) -> Any:
    """DECIMAL as a string, as in every adapter."""
    return str(value) if isinstance(value, decimal.Decimal) else value


class DB:
    """Builds and runs one query at a time; the chain is cleared after each run."""

    def __init__(self, table: str = ""):
        self.table = table
        self.query = ""
        self.placeholder: List[Any] = []
        self.limit_val: Optional[int] = None
        self.offset_val: Optional[int] = None
        self.where_and: List[list] = []
        self.where_or: List[list] = []
        self.order: List[Tuple[str, str]] = []
        self.action = "RAW"
        self.qualifier: Optional[str] = None
        self.result: List[Dict] = []
        self.rows = 0
        self.insert_id: Optional[int] = None
        self.insert_ids: List[int] = []

    # ---- running ----------------------------------------------------------

    @staticmethod
    def raw(sql: str, bind: List = None):
        db = DB()
        db.rawsql(sql)
        db.placeholder = list(bind or [])
        return db.exe()

    def exe(self):
        self.bind()
        postgres = get_db_settings().driver == "postgres"
        sql = self.query
        if postgres and self.action == "INSERT":
            sql += " RETURNING *"  # PostgreSQL has no insert id; read the ids back
        conn = _pool().raw_connection()
        try:
            # Dict rows on our cursors only: SQLAlchemy's pool probes the
            # connection with tuple rows.
            if postgres:
                from psycopg.rows import dict_row
                cursor = conn.cursor(row_factory=dict_row)
            else:
                import pymysql
                cursor = conn.cursor(pymysql.cursors.DictCursor)
            try:
                cursor.execute(driver_sql(sql, postgres), self.placeholder)
                rows = cursor.fetchall() if cursor.description else []
                self.result = [{k: _plain(v) for k, v in dict(r).items()} for r in rows]
                self.rows = cursor.rowcount
                if self.action == "INSERT":
                    if postgres:
                        self.insert_ids = [r["id"] for r in self.result if r.get("id") is not None]
                    else:
                        first = cursor.lastrowid or 0
                        # a multi-row INSERT takes consecutive ids from the first
                        self.insert_ids = list(range(first, first + max(self.rows, 1))) if first else []
                    self.insert_id = self.insert_ids[0] if self.insert_ids else None
                conn.commit()
            finally:
                cursor.close()
        except Exception:
            conn.rollback()
            raise
        finally:
            conn.close()
        self.reset()
        return self

    def reset(self):
        """Clear conditions, order and limit: every query starts from nothing."""
        self.placeholder = []
        self.where_and, self.where_or, self.order = [], [], []
        self.limit_val = self.offset_val = None
        self.action = "RAW"
        return self

    def to_sql(self) -> Dict[str, Any]:
        """The SELECT this builder would run (MySQL form), without running it; resets the builder."""
        if self.action != "SELECT":
            self.sel_set()
        self.bind()
        out = {"sql": self.query, "params": list(self.placeholder)}
        self.reset()
        return out

    def many(self) -> List[Dict]:
        return list(self.result)

    def first(self) -> Optional[Dict]:
        return dict(self.result[0]) if self.result else None

    # ---- building ---------------------------------------------------------

    def bind(self):
        if self.where_and:
            self.query += " WHERE " + self._conditions()
        if self.action == "SELECT":
            self.query += self.order_sql()
        self.query += self.limit_sql()

    def _col(self, column: str) -> str:
        return ident(self.qualifier) + "." + ident(column) if self.qualifier else ident(column)

    def _bind_where(self, data: List[list], join: str) -> str:
        parts = []
        for col, op, val in data:
            if col is RAW:
                parts.append(f" {op} ")
                self.placeholder.extend(val)
            elif isinstance(val, (list, tuple)):
                if not val:  # "IN ()" is invalid SQL: nothing is in an empty list
                    parts.append(" 1 = 1 " if str(op).upper() == "NOT IN" else " 1 = 0 ")
                    continue
                parts.append(f" {self._col(col)} {op} (" + ", ".join("?" for _ in val) + ")")
                self.placeholder.extend(val)
            elif op == "IN":
                parts.append(f" {self._col(col)} IN (?) ")
                self.placeholder.append(val)
            else:
                parts.append(f" {self._col(col)} {op} ? ")
                self.placeholder.append(val)
        return f" {join} ".join(parts)

    def _conditions(self) -> str:
        sql = self._bind_where(self.where_and, "AND")
        if self.where_or:
            sql += " OR " + self._bind_where(self.where_or, "OR")
        return sql

    def where_sql(self, qualifier: Optional[str] = None) -> Dict[str, Any]:
        """The conditions so far, without WHERE; the chain is kept."""
        saved = self.placeholder
        self.placeholder, self.qualifier = [], qualifier
        try:
            sql = self._conditions().strip() if self.where_and else ""
            return {"sql": sql, "params": self.placeholder}
        finally:
            self.placeholder, self.qualifier = saved, None

    def order_sql(self, qualifier: Optional[str] = None) -> str:
        if not self.order:
            return ""
        ref = (lambda c: ident(qualifier) + "." + ident(c)) if qualifier else ident
        return " ORDER BY " + ", ".join(f"{ref(c)} {d}" for c, d in self.order)

    def limit_sql(self) -> str:
        return (f" LIMIT {self.limit_val}" if self.limit_val else "") + (f" OFFSET {self.offset_val}" if self.offset_val else "")

    def where(self, where: Dict):
        return self.where_q(where)

    def find(self, value: Any, key: str = "id"):
        return self.find_q(value, key).limit_q(1)

    def create(self, data: Dict):
        return self.in_set().create_q(data).exe()

    def update(self, data: Dict):
        return self.update_q(data).exe()

    def insert(self, data: List[Dict]):
        return self.in_set().insert_q(data).exe()

    def delete(self):
        return self.del_set()

    def upsert(self, data: List[Dict]):
        self.in_set().upsert_q(data).exe()
        return self

    def last_inserted(self):
        """The row the last INSERT created (by its id, not the newest row)."""
        return self.sel_set().where_q({"id": [self.insert_id]}).exe()

    def get_inserted(self):
        """Every row the last INSERT created; call exe() on it."""
        return self.sel_set().where_q({"id": list(self.insert_ids)})

    def find_q(self, value: Any, key: str = "id"):
        self.where_q({key: [value]})
        return self

    def upsert_q(self, data: List[Dict], conflict: Tuple[str, ...] = ("id",)):
        """Insert, or update the existing row. MySQL matches any unique key; PostgreSQL needs one named."""
        self.insert_q(data)
        cols = list(data[0].keys()) if data else []
        if get_db_settings().driver == "postgres":
            sets = [f"{ident(c)} = EXCLUDED.{ident(c)}" for c in cols if c not in conflict]
            self.query += f" ON CONFLICT ({', '.join(ident(c) for c in conflict)}) " + (
                "DO UPDATE SET " + ", ".join(sets) if sets else "DO NOTHING")
        else:
            self.query += " ON DUPLICATE KEY UPDATE " + ", ".join(f"{ident(c)} = VALUES({ident(c)})" for c in cols)
        return self

    def rawsql(self, sql: str):
        self.query += sql
        return self

    def create_q(self, data: Dict):
        cols = list(data.keys())
        self.query += "( " + ",".join(ident(c) for c in cols) + " ) VALUES ( " + ",".join("?" for _ in cols) + " )"
        self.placeholder.extend(data.values())
        return self

    def insert_q(self, data: List[Dict]):
        if data:
            cols = list(data[0].keys())
            self.query += "( " + ",".join(ident(c) for c in cols) + " ) VALUES "
            rows = []
            for row in data:
                rows.append("(" + ",".join("?" for _ in row) + ")")
                self.placeholder.extend(row.values())
            self.query += ",".join(rows)
        return self

    def update_q(self, data: Dict):
        self.action = "UPDATE"
        self.placeholder = list(data.values())
        self.query = f"UPDATE {self.table} SET " + " , ".join(f"{ident(k)} = ?" for k in data.keys())
        return self

    def where_q(self, where: Dict, type: str = "AND"):
        # column-name order, as in every adapter: the SQL never depends on key order
        target = self.where_and if type == "AND" else self.where_or
        for key in sorted(where):
            target.append([key, "IN", where[key]])
        return self

    def where_custom_q(self, where: List[Tuple], type: str = "AND"):
        target = self.where_and if type == "AND" else self.where_or
        for c in where:
            target.append([c[0], c[1], c[2]])
        return self

    def where_raw_q(self, sql: str, params: List[Any], type: str = "AND"):
        """A condition the ORM built itself (where_has). Not for app SQL."""
        (self.where_and if type == "AND" else self.where_or).append([RAW, sql, list(params)])
        return self

    def order_q(self, column: str, direction: str = "ASC"):
        d = direction.upper()
        if d not in ("ASC", "DESC"):
            raise ValueError(f'orderBy direction must be ASC or DESC, got "{direction}"')
        self.order.append((column, d))
        return self

    def sel_set(self, cols: List[str] = None):
        self.action = "SELECT"
        self.placeholder = []
        self.query = f"SELECT {' , '.join(cols or ['*'])} FROM {self.table}"
        return self

    def count_set(self, col: str = "*"):
        self.action = "COUNT"
        self.query = f'SELECT count({col}) AS {ident("count")} FROM {self.table}'
        return self

    def in_set(self):
        self.action = "INSERT"
        self.query = f"INSERT INTO {self.table}"
        return self

    def up_set(self):
        self.action = "UPDATE"
        self.query = f"UPDATE {self.table} SET "
        return self

    def del_set(self):
        self.action = "DELETE"
        self.query = f"DELETE FROM {self.table} "
        return self

    def limit_q(self, limit: Optional[int]):
        self.limit_val = limit
        return self

    def offset_q(self, offset: Optional[int]):
        self.offset_val = offset
        return self
