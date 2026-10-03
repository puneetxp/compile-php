"""
Lightweight PostgreSQL Query Builder
Similar to compile-php DB.php - builds SQL queries dynamically
"""

import os
from typing import Any, Dict, List, Optional, Tuple

import psycopg
from psycopg.rows import dict_row
from pydantic_settings import BaseSettings, SettingsConfigDict
from sqlalchemy import create_engine


class DatabaseSettings(BaseSettings):
    """Database configuration from environment variables"""

    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")

    postgres_server: str = "localhost"
    postgres_db: str = "app"
    postgres_user: str = "postgres"
    postgres_password: str = "postgres"
    postgres_port: int = 5432


# Global settings instance
_db_settings = None


def get_db_settings() -> DatabaseSettings:
    """Get database settings singleton"""
    global _db_settings
    if _db_settings is None:
        _db_settings = DatabaseSettings()
    return _db_settings


# ── Connection pool ──────────────────────────────────────────────────────────
# SQLAlchemy is used ONLY as a connection pool under this DB/Model API: no SQLAlchemy models,
# no create_all, no Alembic. The schema comes from database/Model/*.json via `php setup.php`.
# Before this, every DB() opened a fresh psycopg connection (slow, and it can exhaust
# a small managed-database connection limit). Size per worker process with DB_POOL_SIZE / DB_MAX_OVERFLOW.
_pool_engine = None


def _pool():
    global _pool_engine
    if _pool_engine is None:
        settings = get_db_settings()

        def _connect():
            # Same parameters as the old per-query connect (host may be a unix socket dir)
            return psycopg.connect(
                host=settings.postgres_server,
                dbname=settings.postgres_db,
                user=settings.postgres_user,
                password=settings.postgres_password,
                port=settings.postgres_port,
            )

        _pool_engine = create_engine(
            "postgresql+psycopg://",
            creator=_connect,
            pool_size=int(os.getenv("DB_POOL_SIZE", "2")),
            max_overflow=int(os.getenv("DB_MAX_OVERFLOW", "2")),
            pool_timeout=30,  # wait for a free connection instead of opening more
            pool_pre_ping=True,  # drop dead connections (server restarts, idle timeouts)
            pool_recycle=1800,
        )
    return _pool_engine


class DB:
    """
    PostgreSQL Query Builder - builds SQL queries dynamically
    Does NOT create tables - only generates and executes queries
    """

    def __init__(self, table: str = ""):
        self.table = table
        self.query = ""
        self.placeholder = []
        self.limit_val = None
        self.offset_val = None
        self.where_and = []
        self.where_or = []
        self.result = None
        self.rows = 0

    @staticmethod
    def raw(sql: str, bind: List = None):
        """Execute raw SQL query"""
        db = DB()
        db.rawsql(sql)
        db.placeholder = bind or []
        return db.exe()

    def where(self, where: Dict):
        """Add WHERE clause"""
        return self.where_q(where)

    def find(self, value: Any, key: str = "id"):
        """Find by key"""
        return self.find_q(value, key).limit_q(1)

    def create(self, data: Dict):
        """Create single record"""
        return self.in_set().create_q(data).exe()

    def update(self, data: Dict):
        """Update records"""
        return self.update_q(data).exe()

    def insert(self, data: List[Dict]):
        """Insert multiple records"""
        return self.in_set().insert_q(data).exe()

    def delete(self):
        """Delete records"""
        return self.del_set()

    def upsert(self, data: List[Dict]):
        """Insert or update on conflict"""
        self.in_set().upsert_q(data).exe()
        return self

    def exe(self):
        """Execute query with prepared statements"""
        self.bind()

        # Borrow a pooled connection for this one statement; close() hands it back to the pool.
        conn = _pool().raw_connection()
        cursor = conn.cursor(row_factory=dict_row)

        try:
            # psycopg3 uses %s placeholders, not $1, $2, ...
            # Just replace ? with %s
            pg_query = self.query.replace("?", "%s")

            cursor.execute(pg_query, self.placeholder)

            # Get results if SELECT query
            if cursor.description:
                self.result = cursor.fetchall()
            else:
                self.result = []

            self.rows = cursor.rowcount
            conn.commit()

        except Exception as e:
            conn.rollback()
            raise e
        finally:
            cursor.close()
            conn.close()

        return self

    def bind(self):
        """Bind WHERE, LIMIT, OFFSET to query"""
        if len(self.where_and) > 0 or len(self.where_or) > 0:
            self.query += " WHERE "

            if len(self.where_and) > 0:
                self.bind_where(self.where_and, "AND")

            if len(self.where_or) > 0:
                if len(self.where_and) > 0:
                    self.query += " OR "
                self.bind_where(self.where_or, "OR")

        if self.limit_val and self.limit_val > 0:
            self.query += f" LIMIT {self.limit_val}"

        if self.offset_val and self.offset_val > 0:
            self.query += f" OFFSET {self.offset_val}"

    def bind_where(self, data: List, join: str = "AND"):
        """Bind WHERE conditions"""
        conditions = []

        for value in data:
            col, op, val = value[0], value[1], value[2]

            if isinstance(val, list):
                placeholders = ", ".join(["?" for _ in val])
                conditions.append(f'"{col}" {op} ({placeholders})')
                self.placeholder.extend(val)
            else:
                conditions.append(f'"{col}" {op} ?')
                self.placeholder.append(val)

        self.query += f" {join} ".join(conditions)

    def many(self) -> List[Dict]:
        """Fetch all results"""
        return [dict(row) for row in self.result] if self.result else []

    def first(self) -> Optional[Dict]:
        """Fetch first result"""
        return dict(self.result[0]) if self.result and len(self.result) > 0 else None

    def last_inserted(self):
        """Get last inserted record"""
        # PostgreSQL uses RETURNING clause, but for compatibility we query by last insert id
        return self.sel_set().rawsql(" ORDER BY id DESC ").limit_q(1).exe()

    def get_inserted(self):
        """Get recently inserted records"""
        return self.sel_set().rawsql(" ORDER BY updated_at DESC ").limit_q(self.rows)

    def find_q(self, value: Any, key: str = "id"):
        """Add find condition"""
        self.where_q({key: [value]})
        return self

    def upsert_q(self, data: List[Dict]):
        """Build UPSERT query (PostgreSQL ON CONFLICT)"""
        self.insert_q(data)
        # Assume 'id' is the conflict column
        update_cols = [f'"{k}" = EXCLUDED."{k}"' for k in data[0].keys() if k != "id"]
        self.query += f" ON CONFLICT (id) DO UPDATE SET {', '.join(update_cols)}"
        return self

    def rawsql(self, sql: str):
        """Append raw SQL"""
        self.query += sql
        return self

    def create_q(self, data: Dict):
        """Build CREATE query (single insert)"""
        cols = list(data.keys())
        placeholders = ", ".join(["?" for _ in cols])
        col_names = ", ".join([f'"{c}"' for c in cols])
        self.query += f"({col_names}) VALUES ({placeholders})"
        self.placeholder.extend(data.values())
        return self

    def insert_q(self, data: List[Dict]):
        """Build INSERT query (multiple inserts)"""
        if len(data) > 0:
            cols = list(data[0].keys())
            col_names = ", ".join([f'"{c}"' for c in cols])
            self.query += f"({col_names}) VALUES "

            values = []
            for row in data:
                placeholders = ", ".join(["?" for _ in row])
                values.append(f"({placeholders})")
                self.placeholder.extend(row.values())

            self.query += ", ".join(values)
        return self

    def update_q(self, data: Dict):
        """Build UPDATE query"""
        self.placeholder = []
        set_clause = ", ".join([f'"{k}" = ?' for k in data.keys()])
        self.query = f'UPDATE "{self.table}" SET {set_clause}'
        self.placeholder.extend(data.values())
        return self

    def where_q(self, where: Dict, type: str = "AND"):
        """Add WHERE IN conditions"""
        for key, value in where.items():
            # Ensure value is a list for IN clause
            val_list = value if isinstance(value, list) else [value]
            if type == "AND":
                self.where_and.append([key, "IN", val_list])
            else:
                self.where_or.append([key, "IN", val_list])
        return self

    def where_custom_q(self, where: List[Tuple], type: str = "AND"):
        """Add custom WHERE conditions"""
        for condition in where:
            if type == "AND":
                self.where_and.append([condition[0], condition[1], condition[2]])
            else:
                self.where_or.append([condition[0], condition[1], condition[2]])
        return self

    def sel_set(self, cols: List[str] = None):
        """Build SELECT query"""
        self.placeholder = []
        cols = cols or ["*"]
        self.query = f'SELECT {", ".join(cols)} FROM "{self.table}"'
        return self

    def count_set(self, col: str = "*"):
        """Build COUNT query"""
        self.query = f'SELECT count({col}) FROM "{self.table}"'
        return self

    def in_set(self):
        """Build INSERT INTO"""
        self.query = f'INSERT INTO "{self.table}"'
        return self

    def up_set(self):
        """Build UPDATE"""
        self.query = f'UPDATE "{self.table}" SET '
        return self

    def del_set(self):
        """Build DELETE"""
        self.query = f'DELETE FROM "{self.table}" '
        return self

    def limit_q(self, limit: int):
        """Add LIMIT"""
        self.limit_val = limit
        return self

    def offset_q(self, offset: int):
        """Add OFFSET"""
        self.offset_val = offset
        return self
