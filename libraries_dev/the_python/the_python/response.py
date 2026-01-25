"""Opinionated response helpers for FastAPI handlers."""
from __future__ import annotations

from typing import Any, Dict, Optional

__all__ = [
    "success_response",
    "error_response",
    "not_found_response",
]


def _base_response(success: bool, message: str, data: Any = None, **meta: Any) -> Dict[str, Any]:
    payload: Dict[str, Any] = {
        "success": success,
        "message": message,
    }
    if data is not None:
        payload["data"] = data
    if meta:
        payload["meta"] = meta
    return payload


def success_response(data: Any = None, message: str = "OK", **meta: Any) -> Dict[str, Any]:
    """Return a success envelope with optional metadata."""
    return _base_response(True, message, data, **meta)


def error_response(message: str, data: Any = None, *, code: Optional[str] = None) -> Dict[str, Any]:
    """Return a generic error response structure."""
    payload = _base_response(False, message, data)
    if code is not None:
        payload.setdefault("meta", {})["code"] = code
    return payload


def not_found_response(resource: str = "Resource") -> Dict[str, Any]:
    """Convenience helper for 404 style payloads."""
    return error_response(f"{resource} not found", code="not_found")
