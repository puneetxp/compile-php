"""
Lightweight ORM Model Base Class
Similar to compile-php Model.php - provides fluent query interface
"""

from typing import Any, Dict, List, Optional, Type

from app.core.db import DB, ident


class _chain:
    """
    A method usable on the class (starts a query on a new instance) and on an
    instance (continues that instance's query). A plain classmethod would
    start over on every call, so Model.where(a).where(b) lost a.
    """

    def __init__(self, fn):
        self.fn = fn
        self.__doc__ = fn.__doc__

    def __get__(self, obj, cls):
        def bound(*args, **kwargs):
            return self.fn(obj if obj is not None else cls(), *args, **kwargs)

        bound.__doc__ = self.fn.__doc__
        return bound


class Model:
    """
    Base Model class - provides ORM-like interface
    Does NOT create tables - only provides query building

    Subclasses must define:
    - table: str (table name)
    - fillable: List[str] (allowed fields)
    - relations: Dict (relationship definitions)
    """

    table: str = ""
    name: str = ""
    fillable: List[str] = []
    columns: List[str] = []  # every column, for with_join's select list
    relations: Dict = {}

    def __init__(self):
        self.items = []
        self.singular = False
        self.db = DB(self.table)
        self._has_depth = 0  # nesting level inside where_has (0 = the query itself)
        self._sql_name: Optional[str] = None  # this table's alias inside a where_has
        self.with_relations = []
        self.relation_data = {}
        self.page = {}

    def set_singular(self):
        """Mark as single result"""
        self.singular = True
        return self

    def paginate(self, page_number: int = 1, page_items: int = 25) -> Optional["Model"]:
        """Paginate results"""
        count_result = self.count()
        total = count_result[0]["count"] if count_result else 0

        if total:
            self.page["result"] = total
            self.page["page_number"] = page_number
            self.page["page_items"] = page_items
            self.page["total_pages"] = (total + page_items - 1) // page_items

            offset = (page_number - 1) * page_items
            while offset > total:
                offset -= page_items

            self.db.offset_q(offset).limit_q(page_items)
            return self.get()

        return None

    @classmethod
    def all(cls) -> "Model":
        """Get all records"""
        instance = cls()
        instance.db.sel_set()
        instance.get()
        return instance

    # ---- the chain --------------------------------------------------------
    # where, where_has, where_not_has, order_by and limit start a query on the
    # class (User.where({...})) or continue one on an instance
    # (.where({...})); see _chain. The SQL is the same as every adapter's for
    # the same chain (spec/query_cases.json).

    @_chain
    def where(self, where: Dict, reset: bool = False) -> "Model":
        """Add col IN (...) conditions; calls chain with AND. Columns not on the model are ignored."""
        if reset:
            self.db.reset()
        self._where(where)
        return self

    @_chain
    def where_has(self, relation: str, where: Any = None, type: str = "AND") -> "Model":
        """Keep rows with at least one related row; where is a dict or a function given the related model."""
        return self._relation_exists(relation, where, type, "EXISTS")

    @_chain
    def where_not_has(self, relation: str, where: Any = None, type: str = "AND") -> "Model":
        """Keep rows with no related row matching where."""
        return self._relation_exists(relation, where, type, "NOT EXISTS")

    @_chain
    def order_by(self, column: str, direction: str = "ASC") -> "Model":
        self.db.order_q(column, direction)
        return self

    @_chain
    def limit(self, count: int, offset: int = 0) -> "Model":
        if not isinstance(count, int) or not isinstance(offset, int) or count < 0 or offset < 0:
            raise ValueError(f"limit needs whole numbers >= 0, got {count}, {offset}")
        self.db.limit_q(count).offset_q(offset)
        return self

    def to_sql(self) -> Dict[str, Any]:
        """The SQL and params the chain would run, without running it."""
        return self.db.to_sql()

    def _relation_exists(self, name: str, where: Any, type: str, op: str) -> "Model":
        rel, related = self._related(name)
        # Aliased <table>__<depth>, so relations to the same table and nested
        # calls refer to the right row; the alias depends only on the chain.
        related._has_depth = self._has_depth + 1
        alias = f'{related.table}__{related._has_depth}'
        related._sql_name = alias
        if callable(where):
            where(related)
        elif where:
            related.where(where)
        inner = related.db.where_sql()
        related.db.reset()
        outer = self._sql_name or self.table
        sql = (f"{op} (SELECT 1 FROM {related.table} AS {ident(alias)} WHERE {ident(alias)}.{ident(rel['key'])}"
               f" = {ident(outer)}.{ident(rel['name'])}" + (f" AND ({inner['sql']})" if inner["sql"] else "") + ")")
        self.db.where_raw_q(sql, inner["params"], type)
        return self

    def with_join_sql(self, relations: List[str]) -> Dict[str, Any]:
        """The SELECT with_join(relations) would run: a LEFT JOIN per relation."""
        base = self.table
        select = self._select_list(base, self._columns())
        joins = ""
        for name in relations:
            rel, related = self._related(name)
            alias = f"r_{name}"
            select += self._select_list(alias, related._columns(), name)
            joins += (f" LEFT JOIN {related.table} {ident(alias)} ON {ident(base)}.{ident(rel['name'])}"
                      f" = {ident(alias)}.{ident(rel['key'])}")
        where = self.db.where_sql(base)
        sql = (f"SELECT {', '.join(select)} FROM {self.table} {ident(base)}{joins}"
               + (f" WHERE {where['sql']}" if where["sql"] else "") + self.db.order_sql(base) + self.db.limit_sql())
        self.db.reset()
        return {"sql": sql, "params": where["params"]}

    def with_join(self, relations: List[str]) -> "Model":
        """Rows with their related rows in one query; related columns nested as row[relation]."""
        q = self.with_join_sql(relations)
        rows = DB.raw(q["sql"], q["params"]).many()
        out = []
        for row in rows:
            base, nested = {}, {}
            for key, value in row.items():
                rel, sep, col = key.partition("__")
                if sep and rel in relations:
                    nested.setdefault(rel, {})[col] = value
                else:
                    base[key] = value
            out.append({**base, **nested})
        self.items = out
        return self

    def _related(self, name: str):
        """A relation's entry and a new instance of its model. The callback may
        return the model class (generated models) or an instance."""
        rel = self.relations.get(name)
        if not rel:
            raise ValueError(f'{self.name}: no relation "{name}" (has: {", ".join(self.relations)})')
        target = rel["callback"]()
        return rel, (target() if isinstance(target, type) else target)

    def _columns(self) -> List[str]:
        return list(self.columns) if self.columns else (["id"] + [c for c in self.fillable if c != "id"])

    @staticmethod
    def _select_list(alias: str, cols: List[str], prefix: Optional[str] = None) -> List[str]:
        if not cols:
            return [ident(alias) + ".*"]
        return [f"{ident(alias)}.{ident(c)} AS {ident(f'{prefix}__{c}' if prefix else c)}" for c in cols]

    def save(self) -> "Model":
        """Save current record (singular)"""
        if not self.singular or not isinstance(self.items, dict):
            # If not singular, maybe you want to update many?
            # But normally save() is on a record.
            return self

        if "id" in self.items and self.items["id"]:
            # Update existing
            id_val = self.items["id"]
            data = {k: v for k, v in self.items.items() if k in self.fillable}
            self.db.where({"id": id_val}).update(data)
        else:
            # Create new
            data = {k: v for k, v in self.items.items() if k in self.fillable}
            self.db.create(data)
            # Fetch last inserted ID
            self.db.last_inserted()
            inserted = self.db.first()
            if inserted:
                self.items = inserted
                self.singular = True
        return self

    @_chain
    def delete(self, where: Optional[Dict] = None):
        """
        Model.delete({...}) deletes the matching rows and returns how many;
        record.delete() deletes that record (True when it had an id). Both were
        defined before, and the second definition silently replaced the first.
        """
        if where is not None:
            return self.db.where(where).delete().exe().rows
        if not self.singular or not isinstance(self.items, dict) or "id" not in self.items:
            return False
        self.db.where({"id": self.items["id"]}).delete().exe()
        return True

    def and_where(self, data: Dict) -> "Model":
        """Add AND WHERE"""
        self.db.where_q(data)
        return self

    def or_where(self, data: Dict) -> "Model":
        """Add OR WHERE"""
        self.db.where_q(data, "OR")
        return self

    def and_where_custom(self, data: List) -> "Model":
        """Add custom AND WHERE"""
        self.db.where_custom_q(data)
        return self

    def or_where_custom(self, data: List) -> "Model":
        """Add custom OR WHERE"""
        self.db.where_custom_q(data, "OR")
        return self

    @classmethod
    def where_custom(cls, where: List) -> "Model":
        """Add custom WHERE clause"""
        return cls()._where_custom(where)

    def get(self) -> "Model":
        """Execute query and get results"""
        self.db.sel_set().exe()
        self.items = self.db.many()
        return self

    def get_null(self) -> Optional["Model"]:
        """Get results or None"""
        self.db.sel_set().exe()
        self.items = self.db.many()
        return self if len(self.items) > 0 else None

    def count(self) -> List[Dict]:
        """Count records"""
        self.db.count_set().exe()
        return self.db.many()

    def first(self, select: List[str] = None) -> Optional["Model"]:
        """Get first result"""
        select = select or ["*"]
        self.items = self.db.sel_set(select).exe().first()

        if self.items:
            self.singular = True
            return self

        return None

    def _where_custom(self, where: List = None) -> "Model":
        """Internal custom where"""
        where = where or []
        self.db.sel_set().where_custom_q(where)
        return self

    def _where(self, where: Dict = None) -> "Model":
        """Internal where"""
        where = where or {}
        filtered = {k: v for k, v in where.items() if k in self.fillable or k == "id"}
        self.db.where(filtered)
        return self

    @classmethod
    def find(cls, value: Any, key: str = "id") -> Optional["Model"]:
        """Find by key"""
        instance = cls()
        instance.db.find(value, key)
        return instance.first()

    def get_inserted(self) -> "Model":
        """Get last inserted record"""
        self.db.last_inserted()
        result = self.db.first()
        if result:
            self.items = result
            self.singular = True
        return self

    def get_all_inserted(self) -> "Model":
        """Get all inserted records"""
        self.db.get_inserted().exe()
        self.items = self.db.many()
        return self

    @classmethod
    def create(cls, data: Dict = None) -> "Model":
        """Create single record"""
        data = data or {}
        instance = cls()
        filtered = {k: v for k, v in data.items() if k in instance.fillable}
        instance.db.create(filtered)
        return instance

    @classmethod
    def insert(cls, data: List[Dict]) -> "Model":
        """Insert multiple records"""
        instance = cls()
        filtered_data = []
        for row in data:
            filtered_data.append({k: v for k, v in row.items() if k in instance.fillable})
        instance.db.insert(filtered_data)
        return instance

    @classmethod
    def upsert(cls, data: List[Dict]) -> "Model":
        """Upsert records"""
        return cls()._upsert(data)

    def update(self, data: Dict) -> "Model":
        """Update records"""
        filtered = {k: v for k, v in data.items() if k in self.fillable}
        self.db.update(filtered)
        return self

    def _upsert(self, data: List[Dict]) -> "Model":
        """Fillable columns plus id: without the id an upsert could only insert."""
        filtered_data = []
        for row in data:
            clean = {k: v for k, v in row.items() if k in self.fillable}
            if row.get("id") is not None:
                clean["id"] = row["id"]
            filtered_data.append(clean)
        self.db.upsert(filtered_data)
        return self

    def delete_current(self) -> "Model":
        """Delete current records"""
        self.db.delete()
        return self

    def clean(self, data: List[Dict]) -> List[Dict]:
        """Clean data to only fillable fields"""
        return [{k: v for k, v in item.items() if k in self.fillable} for item in data]

    def to_dict(self) -> Dict | List[Dict]:
        """Convert to dictionary"""
        return self.items

    def to_json(self) -> str:
        """Convert to JSON string"""
        import json

        return json.dumps(self.items, default=str)

    def with_rel(self, relations: List | str, first: bool = True) -> "Model":
        """Eager load relationships"""
        if not self.items and not self.singular:
            return self

        if first:
            self.with_relations = relations if isinstance(relations, list) else [relations]

        result = {}

        if isinstance(relations, list):
            for rel in relations:
                if isinstance(rel, dict):
                    for key, value in rel.items():
                        rel_class = self.relation(key)
                        if rel_class:
                            self.relation_data[key] = {"class": rel_class.with_rel(value, False)}
                            result[key] = rel_class.to_dict()
                else:
                    rel_class = self.relation(rel)
                    if rel_class:
                        self.relation_data[rel] = {"class": rel_class}
                        result[rel] = rel_class.to_dict()
        else:
            rel_class = self.relation(relations)
            if rel_class:
                self.relation_data[relations] = {"class": rel_class}
                result[relations] = rel_class.to_dict()

        # Combine with main items
        table_name = self.table
        if self.singular:
            result[table_name] = [self.items]
        else:
            result[table_name] = self.items

        self.items = result
        return self

    def relation(self, rel_name: str) -> Optional["Model"]:
        """Load relationship"""
        if rel_name not in self.relations:
            return None

        rel_config = self.relations[rel_name]

        # Get foreign key values
        if self.singular:
            fk_values = [self.items.get(rel_config["name"])]
        else:
            fk_values = [item.get(rel_config["name"]) for item in self.items]

        # Filter out None values
        fk_values = [v for v in fk_values if v is not None]

        if not fk_values:
            return None

        # Query related model - handle string references, lambdas, and direct class references
        related_class = rel_config["callback"]

        if isinstance(related_class, str):
            # String reference - import dynamically
            import importlib

            module = importlib.import_module(f"app.orm.{related_class.lower()}")
            related_class = getattr(module, related_class)
        elif callable(related_class) and not isinstance(related_class, type):
            # It's a lambda, call it to get the class
            related_class = related_class()

        where = {rel_config["key"]: fk_values}

        return related_class.where(where).get()

    def sort_relations(self) -> "Model":
        """Sort relationship data into parent records"""
        if not self.with_relations:
            return self

        # This is a simplified version - full implementation would nest relations properly
        if self.singular and isinstance(self.items, dict):
            table_name = self.table
            if table_name in self.items:
                self.items = self.items[table_name][0] if self.items[table_name] else {}

        return self

    def __getattr__(self, name: str) -> Any:
        """Proxy attribute access to items if singular"""
        # Avoid recursion during initialization
        if name == "items" or name == "singular":
            raise AttributeError(name)

        if (
            hasattr(self, "singular")
            and self.singular
            and isinstance(self.items, dict)
            and name in self.items
        ):
            return self.items[name]
        raise AttributeError(f"'{self.__class__.__name__}' object has no attribute '{name}'")

    def __setattr__(self, name: str, value: Any) -> None:
        """Proxy attribute writing to items if singular and in fillable"""
        if (
            name != "items"
            and name != "singular"
            and hasattr(self, "singular")
            and self.singular
            and isinstance(self.items, dict)
            and (name in self.fillable or name == "id")
        ):
            self.items[name] = value
        else:
            super().__setattr__(name, value)

    def __getitem__(self, key: Any) -> Any:
        """Dict-like or list-like access"""
        if self.singular and isinstance(self.items, dict):
            return self.items[key]
        if isinstance(self.items, list):
            return self.items[key]
        raise KeyError(key)

    def __setitem__(self, key: Any, value: Any) -> None:
        """Dict-like access for singular items"""
        if self.singular and isinstance(self.items, dict):
            self.items[key] = value
        else:
            raise TypeError(f"'{self.__class__.__name__}' object does not support item assignment")

    def __iter__(self):
        """Make model iterable if it contains a list of items"""
        if hasattr(self, "singular") and not self.singular and isinstance(self.items, list):
            return iter(self.items)
        # If singular, return empty or self as a list
        return iter([])

    def __len__(self) -> int:
        """Return number of items"""
        if isinstance(self.items, list):
            return len(self.items)
        return 1 if self.items else 0

    def keys(self):
        """Support dict-like keys() for serialization"""
        if hasattr(self, "singular") and self.singular and isinstance(self.items, dict):
            return self.items.keys()
        return []

    def __str__(self) -> str:
        """String representation"""
        return self.to_json()

    def __repr__(self) -> str:
        """Representation"""
        items_count = len(self.items) if isinstance(self.items, list) else (1 if self.items else 0)
        return f"<{self.__class__.__name__} items={items_count}>"
