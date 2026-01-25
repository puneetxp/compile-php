"""In-memory data access helpers used by the generated FastAPI project."""

from __future__ import annotations

from copy import deepcopy
from typing import Any, Dict, Iterable, List, Optional


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
