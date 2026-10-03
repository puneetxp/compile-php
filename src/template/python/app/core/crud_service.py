"""
Base class for the generated per-table services (app/services/<table>_service.py).

Generated services only declare `model`; all CRUD lives here so the ORM is used one correct way.
Pass `owner` (the signed-in user) from /islogin/* controllers to apply app/core/ownership.py rules;
/isuper/* and /ipublic/* controllers call without it.
"""

from typing import Any, Dict, List, Optional

from fastapi import HTTPException

from app.core import ownership
from app.core.db import DB


class CrudService:
    model: Any = None

    @property
    def table(self) -> str:
        return self.model.table

    @property
    def columns(self) -> List[str]:
        return list(self.model.fillable)

    # ── helpers ────────────────────────────────────────────────────────────
    def _scope(self, owner, for_write: bool = False) -> Optional[str]:
        if owner is None:
            return None
        if not for_write and self.table in ownership.SHARED_READ:
            return None
        return ownership.owner_condition(self.table, owner.id, self.columns)

    def _select(
        self, where: str = "", bind: Optional[list] = None, owner=None, for_write: bool = False
    ) -> List[Dict]:
        clauses = [f"({where})"] if where else []
        scope = self._scope(owner, for_write)
        if scope:
            clauses.append(scope)
        sql = f'SELECT t.* FROM "{self.table}" t'
        if clauses:
            sql += " WHERE " + " AND ".join(clauses)
        sql += " ORDER BY t.id DESC"
        return DB.raw(sql, bind or []).result or []

    def _clean(self, data: Dict) -> Dict:
        allowed = set(self.columns)
        return {k: v for k, v in data.items() if k in allowed}

    def _check_parents(self, data: Dict, owner) -> None:
        """Refuse to attach a row to a parent row (see ownership.PARENTS) the user doesn't own."""
        for column, parent_table in ownership.PARENTS.get(self.table, {}).items():
            parent_id = data.get(column)
            if parent_id in (None, ""):
                continue
            rule = ownership.owner_condition(parent_table, owner.id)
            if not rule:
                continue
            rows = DB.raw(
                f'SELECT 1 FROM "{parent_table}" t WHERE t.id = ? AND {rule}', [parent_id]
            ).result
            if not rows:
                raise HTTPException(status_code=404, detail=f"{parent_table} {parent_id} not found")

    # ── CRUD ───────────────────────────────────────────────────────────────
    def all(self, owner=None) -> List[Dict]:
        return self._select(owner=owner)

    def find(self, item_id: int, owner=None, for_write: bool = False) -> Optional[Dict]:
        rows = self._select("t.id = ?", [item_id], owner=owner, for_write=for_write)
        return rows[0] if rows else None

    def where(self, filters: Dict, owner=None) -> List[Dict]:
        filters = self._clean(filters)
        if not filters:
            return self._select(owner=owner)
        where = " AND ".join(f't."{k}" = ?' for k in filters)
        return self._select(where, list(filters.values()), owner=owner)

    def create(self, data: Dict, owner=None) -> Dict:
        data = self._clean(data)
        data.pop("id", None)
        if owner is not None:
            for column in ownership.owner_columns(self.table, self.columns):
                data[column] = owner.id
            self._check_parents(data, owner)
        if not data:
            raise HTTPException(status_code=422, detail="No valid fields to create")
        cols = ", ".join(f'"{k}"' for k in data)
        marks = ", ".join("?" for _ in data)
        rows = DB.raw(
            f'INSERT INTO "{self.table}" ({cols}) VALUES ({marks}) RETURNING *', list(data.values())
        ).result
        return rows[0]

    def update(self, item_id: int, data: Dict, owner=None) -> Optional[Dict]:
        if not self.find(item_id, owner=owner, for_write=True):
            return None
        data = self._clean(data)
        data.pop("id", None)
        if owner is not None:
            for column in ownership.owner_columns(self.table, self.columns):
                data.pop(column, None)  # can't hand a row to someone else
            self._check_parents(data, owner)
        if not data:
            return self.find(item_id)
        sets = ", ".join(f'"{k}" = ?' for k in data)
        rows = DB.raw(
            f'UPDATE "{self.table}" SET {sets}, "updated_at" = CURRENT_TIMESTAMP WHERE id = ? RETURNING *',
            list(data.values()) + [item_id],
        ).result
        return rows[0] if rows else None

    def upsert(self, data: Dict, owner=None) -> Optional[Dict]:
        if data.get("id"):
            return self.update(data["id"], data, owner=owner)
        return self.create(data, owner=owner)

    def delete(self, item_id: int, owner=None) -> bool:
        if not self.find(item_id, owner=owner, for_write=True):
            return False
        return (DB.raw(f'DELETE FROM "{self.table}" WHERE id = ?', [item_id]).rows or 0) > 0
