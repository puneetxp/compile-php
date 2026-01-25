"""Authentication helpers featuring password hashing & verification."""
from __future__ import annotations

import hashlib
import os
from dataclasses import dataclass
from typing import Protocol

try:  # pragma: no cover - optional dependency
    import bcrypt  # type: ignore
except ImportError:  # pragma: no cover
    bcrypt = None  # type: ignore

__all__ = ["AuthService"]


class _Hasher(Protocol):
    def hash(self, password: str) -> str: ...

    def verify(self, password: str, hashed: str) -> bool: ...


@dataclass
class _BcryptHasher:
    rounds: int = 12

    def hash(self, password: str) -> str:  # pragma: no cover - simple wrapper
        salt = bcrypt.gensalt(self.rounds)
        return bcrypt.hashpw(password.encode(), salt).decode()

    def verify(self, password: str, hashed: str) -> bool:  # pragma: no cover
        return bcrypt.checkpw(password.encode(), hashed.encode())


@dataclass
class _Sha256Hasher:
    """Fallback hasher when bcrypt is unavailable."""

    def hash(self, password: str) -> str:
        salt = os.urandom(8).hex()
        digest = hashlib.sha256((salt + password).encode()).hexdigest()
        return f"sha256${salt}${digest}"

    def verify(self, password: str, hashed: str) -> bool:
        _, salt, digest = hashed.split("$")
        comparison = hashlib.sha256((salt + password).encode()).hexdigest()
        return comparison == digest


class AuthService:
    """Tiny facade for password hashing / verification."""

    _hasher: _Hasher

    def __init__(self) -> None:
        self._hasher = _BcryptHasher() if bcrypt else _Sha256Hasher()

    def hash_password(self, password: str) -> str:
        return self._hasher.hash(password)

    def verify_password(self, password: str, hashed: str) -> bool:
        return self._hasher.verify(password, hashed)


auth = AuthService()
