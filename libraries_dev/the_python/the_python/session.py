"""Ultra lightweight in-memory session store for prototypes."""
from __future__ import annotations

from typing import Any, Dict

__all__ = ["session_store"]


class SessionStore:
    def __init__(self) -> None:
        self._store: Dict[str, Dict[str, Any]] = {}

    def get(self, session_id: str) -> Dict[str, Any]:
        return self._store.setdefault(session_id, {})

    def put(self, session_id: str, payload: Dict[str, Any]) -> None:
        self._store[session_id] = payload

    def forget(self, session_id: str) -> None:
        self._store.pop(session_id, None)


session_store = SessionStore()
