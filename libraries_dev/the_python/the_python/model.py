"""Data access helpers — in-memory ModelService and SQL-backed Model base class."""

from __future__ import annotations

from copy import deepcopy
from typing import Any, Callable, Dict, Iterable, List, Optional

__all__ = ["ModelService", "Model"]

# Relation definition shape:
#   {
#       'account': {
#           'table':    'accounts',
#           'name':     'account_id',        # FK column on THIS table
#           'key':      'id',                # PK column on RELATED table
#           'callback': lambda: Account$,   # lazy ref to sibling Model instance
#       }
#   }
RelationDef = Dict[str, Any]


# =============================================================================
# ModelService — in-memory prototype data layer (unchanged)
# =============================================================================

class ModelService:
    """Very small in-memory data layer.

    The goal is to let the generated FastAPI app boot instantly without requiring
    a database. Developers can later swap this out for a real repository.
    """

    def __init__(self, table: str):
        self.table = table
        self._items: List[Dict[str, Any]] = []
        self._sequence: int = 1

    # ------------------------------------------------------------------
    # Internal helpers
    # ------------------------------------------------------------------
    def _clone(self, value: Any) -> Any:
        return deepcopy(value)

    def _next_id(self) -> int:
        current = self._sequence
        self._sequence += 1
        return current

    def _normalize_id(self, item_id: Any) -> Any:
        try:
            return int(item_id)
        except (TypeError, ValueError):
            return item_id

    # ------------------------------------------------------------------
    # CRUD helpers
    # ------------------------------------------------------------------
    def all(self) -> List[Dict[str, Any]]:
        return self._clone(self._items)

    def find(self, item_id: Any) -> Optional[Dict[str, Any]]:
        target = self._normalize_id(item_id)
        for item in self._items:
            if item.get("id") == target:
                return self._clone(item)
        return None

    def create(self, payload: Dict[str, Any]) -> Dict[str, Any]:
        record = self._clone(payload)
        if record.get("id") is None:
            record["id"] = self._next_id()
        else:
            record["id"] = self._normalize_id(record["id"])
        self._items.append(record)
        return self._clone(record)

    def update(self, item_id: Any, payload: Dict[str, Any]) -> Optional[Dict[str, Any]]:
        target = self._normalize_id(item_id)
        for index, item in enumerate(self._items):
            if item.get("id") == target:
                updated = {**item, **self._clone(payload)}
                updated["id"] = target
                self._items[index] = updated
                return self._clone(updated)
        return None

    def delete(self, item_id: Any) -> bool:
        target = self._normalize_id(item_id)
        before = len(self._items)
        self._items = [item for item in self._items if item.get("id") != target]
        return len(self._items) < before

    # ------------------------------------------------------------------
    # Convenience helpers
    # ------------------------------------------------------------------
    def where(self, filters: Dict[str, Any]) -> List[Dict[str, Any]]:
        if not filters:
            return self.all()

        def matches(item: Dict[str, Any]) -> bool:
            for key, expected in filters.items():
                actual = item.get(key)
                if isinstance(expected, Iterable) and not isinstance(expected, (str, bytes)):
                    if actual not in expected:
                        return False
                else:
                    if actual != expected:
                        return False
            return True

        return [self._clone(item) for item in self._items if matches(item)]

    def upsert(self, payload: Dict[str, Any]) -> Dict[str, Any]:
        item_id = payload.get("id")
        if item_id is not None:
            updated = self.update(item_id, payload)
            if updated:
                return updated
        return self.create(payload)

    def seed(self, rows: List[Dict[str, Any]]) -> None:
        for row in rows:
            self.create(row)

    def reset(self) -> None:
        self._items.clear()
        self._sequence = 1


# =============================================================================
# _DB — internal SQLAlchemy Core query builder
# =============================================================================

class _DB:
    """Internal SQLAlchemy Core query builder used by Model.

    Mirrors the role of the Deno DB() helper — builds parameterised SQL
    strings and executes them against the shared engine.
    """

    def __init__(self, table: str, fillable: List[str]) -> None:
        self._table = table
        self._fillable = fillable
        self._where_parts: List[str] = []
        self._params: Dict[str, Any] = {}
        self._order: Optional[str] = None
        self._limit: Optional[int] = None
        self._offset: Optional[int] = None

    # ------------------------------------------------------------------
    # Chainable query builders
    # ------------------------------------------------------------------
    def where(self, filters: Dict[str, Any], joiner: str = "AND") -> "_DB":
        """Add WHERE conditions.

        Values that are lists become ``IN (...)``;
        scalars become ``= :param``.
        """
        for key, val in filters.items():
            safe_key = key.replace("`", "")
            param_name = f"_w_{safe_key}_{len(self._params)}"
            if isinstance(val, (list, tuple, set)):
                in_params: Dict[str, Any] = {}
                placeholders = []
                for i, v in enumerate(val):
                    pname = f"_in_{safe_key}_{i}_{len(self._params)}"
                    in_params[pname] = v
                    placeholders.append(f":{pname}")
                self._params.update(in_params)
                self._where_parts.append(f"`{safe_key}` IN ({', '.join(placeholders)})")
            else:
                self._params[param_name] = val
                self._where_parts.append(f"`{safe_key}` = :{param_name}")
        return self

    def limit(self, n: int) -> "_DB":
        self._limit = n
        return self

    def offset(self, n: int) -> "_DB":
        self._offset = n
        return self

    def order_by(self, col: str, direction: str = "ASC") -> "_DB":
        self._order = f"`{col}` {direction.upper()}"
        return self

    # ------------------------------------------------------------------
    # SQL builders
    # ------------------------------------------------------------------
    def _where_clause(self) -> str:
        if not self._where_parts:
            return ""
        return " WHERE " + " AND ".join(self._where_parts)

    def _select_sql(self, cols: List[str]) -> str:
        col_str = (
            ", ".join(f"`{c}`" if c != "*" else "*" for c in cols)
            if cols
            else "*"
        )
        sql = f"SELECT {col_str} FROM `{self._table}`"
        sql += self._where_clause()
        if self._order:
            sql += f" ORDER BY {self._order}"
        if self._limit is not None:
            sql += f" LIMIT {self._limit}"
        if self._offset is not None:
            sql += f" OFFSET {self._offset}"
        return sql

    # ------------------------------------------------------------------
    # Execution helpers
    # ------------------------------------------------------------------
    def _execute(
        self, sql: str, params: Optional[Dict[str, Any]] = None
    ) -> List[Dict[str, Any]]:
        from .db import engine
        from sqlalchemy import text

        with engine.connect() as conn:
            result = conn.execute(text(sql), params if params is not None else self._params)
            return [dict(row._mapping) for row in result]

    def _execute_write(
        self, sql: str, params: Optional[Dict[str, Any]] = None
    ) -> Any:
        from .db import engine
        from sqlalchemy import text

        with engine.begin() as conn:
            return conn.execute(text(sql), params or {})

    # ------------------------------------------------------------------
    # Public query methods
    # ------------------------------------------------------------------
    def fetch_many(self, cols: List[str] = []) -> List[Dict[str, Any]]:
        return self._execute(self._select_sql(cols))

    def fetch_first(self, cols: List[str] = []) -> Optional[Dict[str, Any]]:
        rows = self._execute(self._select_sql(cols))
        return rows[0] if rows else None

    def fetch_count(self) -> int:
        sql = f"SELECT COUNT(*) AS _cnt FROM `{self._table}`{self._where_clause()}"
        rows = self._execute(sql)
        return int(rows[0]["_cnt"]) if rows else 0

    def do_create(self, data: Dict[str, Any]) -> int:
        """INSERT a single row; returns lastrowid."""
        cols = list(data.keys())
        col_str = ", ".join(f"`{c}`" for c in cols)
        val_str = ", ".join(f":{c}" for c in cols)
        sql = f"INSERT INTO `{self._table}` ({col_str}) VALUES ({val_str})"
        result = self._execute_write(sql, data)
        return result.lastrowid  # type: ignore[union-attr]

    def do_insert(self, rows: List[Dict[str, Any]]) -> None:
        """Bulk INSERT."""
        if not rows:
            return
        cols = list(rows[0].keys())
        col_str = ", ".join(f"`{c}`" for c in cols)
        val_str = ", ".join(f":{c}" for c in cols)
        sql = f"INSERT INTO `{self._table}` ({col_str}) VALUES ({val_str})"
        from .db import engine
        from sqlalchemy import text

        with engine.begin() as conn:
            conn.execute(text(sql), rows)

    def do_update(self, data: Dict[str, Any]) -> None:
        """UPDATE rows matching current WHERE conditions."""
        set_parts = ", ".join(f"`{k}` = :_set_{k}" for k in data)
        params = {f"_set_{k}": v for k, v in data.items()}
        params.update(self._params)
        sql = f"UPDATE `{self._table}` SET {set_parts}{self._where_clause()}"
        self._execute_write(sql, params)

    def do_upsert(self, rows: List[Dict[str, Any]]) -> None:
        """INSERT … ON DUPLICATE KEY UPDATE for each row."""
        if not rows:
            return
        cols = list(rows[0].keys())
        col_str = ", ".join(f"`{c}`" for c in cols)
        val_str = ", ".join(f":{c}" for c in cols)
        update_str = ", ".join(f"`{c}` = VALUES(`{c}`)" for c in cols)
        sql = (
            f"INSERT INTO `{self._table}` ({col_str}) VALUES ({val_str}) "
            f"ON DUPLICATE KEY UPDATE {update_str}"
        )
        from .db import engine
        from sqlalchemy import text

        with engine.begin() as conn:
            conn.execute(text(sql), rows)

    def do_delete(self, where: Dict[str, Any]) -> None:
        self.where(where)
        sql = f"DELETE FROM `{self._table}`{self._where_clause()}"
        self._execute_write(sql, self._params)


# =============================================================================
# Model — SQL-backed abstract base class (mirrors Deno / PHP pattern)
# =============================================================================

class Model:
    """SQL-backed base class that mirrors the Deno / PHP ``Model<T>`` pattern.

    Subclass it and call ``super().__init__()`` with the same constructor
    signature as the Deno version::

        from the_python import Model

        class Standard(Model):
            def __init__(self):
                super().__init__(
                    name='account_attribute_value',
                    table='account_attribute_values',
                    nullable=['id'],
                    fillable=['enable', 'name', 'account_attribute_id', 'account_id'],
                    model=['id', 'created_at', 'updated_at', 'enable', 'name',
                           'account_attribute_id', 'account_id'],
                    relations={
                        'account_attribute': {
                            'table': 'account_attributes',
                            'callback': lambda: Account_attribute$,
                            'name': 'account_attribute_id',
                            'key': 'id',
                        },
                        'account': {
                            'table': 'accounts',
                            'callback': lambda: Account$,
                            'name': 'account_id',
                            'key': 'id',
                        },
                    }
                )

        Account_attribute_value$ = Standard()

    Then use it::

        items = Account_attribute_value$.where({'account_id': 5}).get().array()
        single = Account_attribute_value$.find(1).item

    Note:
        Python reserves the keyword ``with``, so the Deno ``with()`` method
        is exposed here as ``with_()``.
    """

    def __init__(
        self,
        name: str = "",
        table: str = "",
        nullable: Optional[List[str]] = None,
        fillable: Optional[List[str]] = None,
        model: Optional[List[str]] = None,
        relations: Optional[Dict[str, RelationDef]] = None,
    ) -> None:
        self.name = name
        self.table = table
        self.nullable: List[str] = list(nullable or [])
        self.fillable: List[str] = list(fillable or [])
        self.model: List[str] = list(model or [])
        self.relations: Dict[str, RelationDef] = dict(relations or {})

        # Runtime state (reset between query chains)
        self.items: List[Dict[str, Any]] = []
        self.item: Optional[Dict[str, Any]] = None
        self.singular: bool = False
        self.page: Dict[str, Any] = {}
        self._with: Any = None
        self._relationship: Dict[str, Any] = {}
        self._db: _DB = self._fresh_db()

    # ------------------------------------------------------------------
    # Internal helpers
    # ------------------------------------------------------------------
    def _fresh_db(self) -> _DB:
        return _DB(self.table, self.fillable)

    def _reset(self) -> None:
        """Reset query-builder state so the instance can be reused."""
        self._db = self._fresh_db()
        self.singular = False
        self._relationship = {}

    # ------------------------------------------------------------------
    # WHERE / filter chain
    # ------------------------------------------------------------------
    def where(self, filters: Dict[str, Any]) -> "Model":
        """Add AND WHERE conditions (values may be scalars or lists for IN)."""
        self._db.where(filters)
        return self

    def and_where(self, filters: Dict[str, Any]) -> "Model":
        self._db.where(filters, "AND")
        return self

    def or_where(self, filters: Dict[str, Any]) -> "Model":
        self._db.where(filters, "OR")
        return self

    def order_by(self, col: str, direction: str = "ASC") -> "Model":
        self._db.order_by(col, direction)
        return self

    def limit(self, n: int) -> "Model":
        self._db.limit(n)
        return self

    def offset(self, n: int) -> "Model":
        self._db.offset(n)
        return self

    # ------------------------------------------------------------------
    # Query execution
    # ------------------------------------------------------------------
    def get(self) -> "Model":
        """Execute SELECT and populate ``self.items``."""
        self.items = self._db.fetch_many(self.model)
        self._reset()
        return self

    def all(self) -> "Model":
        """SELECT all rows (no WHERE)."""
        self._reset()
        self.items = self._db.fetch_many(self.model)
        return self

    def first(self, select: Optional[List[str]] = None) -> Optional[Dict[str, Any]]:
        """Return the first matching row or ``None``."""
        row = self._db.fetch_first(select or self.model)
        self._reset()
        return row

    def find(self, value: Any, key: str = "id") -> "Model":
        """WHERE key=value — populates ``self.item`` and sets ``singular=True``."""
        self._reset()
        self._db.where({key: value})
        self.item = self._db.fetch_first(self.model)
        self.singular = True
        self._reset()
        return self

    def count(self) -> int:
        """Return COUNT(*) for the current WHERE conditions."""
        n = self._db.fetch_count()
        self._reset()
        return n

    def get_null(self) -> Optional["Model"]:
        """Like ``get()`` but returns ``None`` when the result set is empty."""
        self.get()
        return self if self.items else None

    # ------------------------------------------------------------------
    # Writes
    # ------------------------------------------------------------------
    def create(self, data: Dict[str, Any]) -> "Model":
        """INSERT a sanitized row; re-fetches it into ``self.item``."""
        clean = self.sanitize(data)
        last_id = self._db.do_create(clean)
        self._reset()
        self._db.where({"id": last_id})
        self.item = self._db.fetch_first(self.model)
        self._reset()
        return self

    def insert(self, rows: List[Dict[str, Any]]) -> "Model":
        """Bulk INSERT sanitized rows."""
        self._db.do_insert(self.clean(rows))
        self._reset()
        return self

    def update(self, data: Dict[str, Any]) -> "Model":
        """UPDATE rows matching current WHERE chain with sanitized data."""
        self._db.do_update(self.sanitize(data))
        self._reset()
        return self

    def upsert(self, rows: List[Dict[str, Any]]) -> "Model":
        """INSERT … ON DUPLICATE KEY UPDATE for each row."""
        self._db.do_upsert(self.clean(rows))
        self._reset()
        return self

    def delete(self, where: Dict[str, Any]) -> "Model":
        """DELETE WHERE the given filters match."""
        self._db.do_delete(where)
        self._reset()
        return self

    # ------------------------------------------------------------------
    # Sanitize / clean
    # ------------------------------------------------------------------
    def sanitize(self, item: Dict[str, Any]) -> Dict[str, Any]:
        """Filter ``item`` to only fillable keys."""
        return {k: v for k, v in item.items() if k in self.fillable}

    def clean(self, rows: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Sanitize a list of dicts."""
        return [self.sanitize(r) for r in rows]

    # ------------------------------------------------------------------
    # Output
    # ------------------------------------------------------------------
    def array(self) -> List[Dict[str, Any]]:
        """Return ``self.items`` as a plain list."""
        return self.items

    def __repr__(self) -> str:
        return f"<Model table={self.table!r} items={len(self.items)}>"

    # ------------------------------------------------------------------
    # Eager-load relations  (Deno `with()` → Python `with_()`)
    # ------------------------------------------------------------------
    def _load_relation(self, rel_name: str) -> Optional["Model"]:
        """Fetch related rows for the current ``self.items``."""
        rel = self.relations.get(rel_name)
        if rel is None:
            raise KeyError(
                f"Relation {rel_name!r} not defined on {self.__class__.__name__}"
            )
        fk_col: str = rel["name"]   # local FK column
        pk_col: str = rel["key"]    # remote PK column
        sibling: "Model" = rel["callback"]()

        if self.singular:
            fk_values = [self.item[fk_col]] if self.item and self.item.get(fk_col) is not None else []
        else:
            fk_values = [row[fk_col] for row in self.items if row.get(fk_col) is not None]

        if not fk_values:
            return None

        return sibling.where({pk_col: fk_values}).get()

    def with_(self, data: Any, _first: bool = True) -> "Model":
        """Eager-load one or more relations.

        Mirrors the Deno ``with()`` API (renamed because ``with`` is a
        Python reserved keyword).

        Args:
            data: A relation name (str), a list of relation names, or a nested
                  dict ``{"relation": ["sub_relation"]}`` for deep loading.

        Returns:
            ``self`` — chain ``.sort()`` afterwards to embed related rows.
        """
        if not self.items and not self.singular:
            return self

        if _first:
            self._with = data if isinstance(data, list) else [data]

        x: Dict[str, Any] = {}

        if isinstance(data, list):
            for item in data:
                if isinstance(item, dict):
                    for rel_name, sub_rels in item.items():
                        loaded = self._load_relation(rel_name)
                        if loaded:
                            loaded.with_(sub_rels, _first=False)
                        self._relationship[rel_name] = loaded
                        x.update({rel_name: self._isnull(loaded)})
                else:
                    loaded = self._load_relation(item)
                    self._relationship[item] = loaded
                    x[item] = self._isnull(loaded)
        elif isinstance(data, dict):
            for rel_name, sub_rels in data.items():
                loaded = self._load_relation(rel_name)
                if loaded:
                    loaded.with_(sub_rels, _first=False)
                self._relationship[rel_name] = loaded
                x[rel_name] = self._isnull(loaded)
        else:
            loaded = self._load_relation(data)
            self._relationship[data] = loaded
            x[data] = self._isnull(loaded)

        x[self.name] = [self.item] if self.singular else self.items
        self.items = x
        return self

    def _isnull(self, model_instance: Optional["Model"]) -> Any:
        return [] if model_instance is None else model_instance.items

    # ------------------------------------------------------------------
    # Sort — bind related rows into parent items
    # ------------------------------------------------------------------
    def sort(self) -> "Model":
        """Embed eager-loaded relation data into each parent item.

        Call this after ``with_()`` to get a fully nested structure.
        """
        if self._with and isinstance(self.items, dict):
            self.items = self._sortout(self._with, self.items.get(self.name, []))
        if self.singular:
            self.items = (
                self.items[0]
                if isinstance(self.items, list) and self.items
                else self.items
            )
        return self

    def _sortout(
        self,
        relations: Any,
        data: List[Dict[str, Any]],
        base: Any = None,
    ) -> List[Dict[str, Any]]:
        for rel in relations:
            if isinstance(rel, dict):
                data = self._filter_relations(rel, data, base or self.items)
            else:
                data = self._filter_relation(rel, data, base or self.items)
        return data

    def _filter_relation(
        self,
        rel_name: str,
        data: List[Dict[str, Any]],
        base: Any,
    ) -> List[Dict[str, Any]]:
        rel = self.relations[rel_name]
        fk_col: str = rel["name"]
        pk_col: str = rel["key"]
        related_rows: List[Dict[str, Any]] = (
            base[rel_name]
            if isinstance(base, dict)
            else self.items.get(rel_name, [])
        ) or []

        return [
            {
                **item,
                rel_name: [r for r in related_rows if r.get(pk_col) == item.get(fk_col)],
            }
            for item in data
        ]

    def _filter_relations(
        self,
        rel_dict: Dict[str, Any],
        data: List[Dict[str, Any]],
        base: Any,
    ) -> List[Dict[str, Any]]:
        for rel_name, sub_rels in rel_dict.items():
            data = self._filter_relation(rel_name, data, base)
            sibling = self._relationship.get(rel_name)
            if sibling:
                for idx, item in enumerate(data):
                    data[idx][rel_name] = sibling._sortout(
                        sub_rels if isinstance(sub_rels, list) else [sub_rels],
                        item[rel_name],
                        base,
                    )
        return data

    # ------------------------------------------------------------------
    # Pagination
    # ------------------------------------------------------------------
    def paginate(
        self,
        page_number: int = 1,
        page_items: int = 25,
    ) -> Optional["Model"]:
        """Paginate results.

        Inject ``self.page["pageNumber"]`` / ``self.page["pageItems"]`` from
        your FastAPI query params before calling this method if needed.
        """
        page_number = int(self.page.get("pageNumber", page_number))
        page_items = int(self.page.get("pageItems", page_items))
        total = self.count()
        if total:
            self.page["result"] = total
            self.page["pageNumber"] = page_number
            self.page["pageItems"] = page_items
            self.page["totalpages"] = total / page_items
            offset = (page_number - 1) * page_items
            while offset > total:
                offset -= page_items
            self._db.offset(offset).limit(page_items)
            return self.get()
        return None
