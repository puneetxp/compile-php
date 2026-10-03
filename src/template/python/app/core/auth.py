"""
Authentication for the generated FastAPI app.

- Tokens: `Authorization: Bearer <JWT>` (HS256). Set JWT_SECRET in the environment.
- Users come from the framework tables: users, active_roles, roles (same as the PHP/Deno runtimes).
- Passwords use sha3-256 hex, like the PHP runtime (puneetxp/the), so both can share a users table.
- `isuper` = user id 1 or an active_roles row pointing at the role named "isuper".

Generated routers import get_current_active_user (islogin/custom roles) and get_current_admin (isuper).
Replace this module if the project uses another identity provider; keep those two function names.
"""

import hashlib
import os
import time
from dataclasses import dataclass, field
from typing import List, Optional

import jwt
from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.core.db import DB

JWT_ALGORITHM = "HS256"
JWT_TTL_SECONDS = int(os.getenv("JWT_TTL_SECONDS", str(60 * 60 * 24)))

_bearer = HTTPBearer(auto_error=False)


@dataclass
class CurrentUser:
    id: int
    name: str
    email: str
    enable: int = 1
    roles: List[str] = field(default_factory=list)


def _secret() -> str:
    secret = os.getenv("JWT_SECRET")
    if not secret:
        raise HTTPException(status_code=500, detail="JWT_SECRET is not configured")
    return secret


def hash_password(password: str) -> str:
    return hashlib.sha3_256(password.encode()).hexdigest()


def create_access_token(user_id: int) -> str:
    now = int(time.time())
    return jwt.encode({"sub": str(user_id), "iat": now, "exp": now + JWT_TTL_SECONDS}, _secret(), algorithm=JWT_ALGORITHM)


def user_roles(user_id: int) -> List[str]:
    rows = DB.raw(
        'SELECT r.name FROM "active_roles" a JOIN "roles" r ON r.id = a.role_id WHERE a.user_id = ?', [user_id]
    ).result or []
    roles = [r["name"] for r in rows]
    if int(user_id) == 1 and "isuper" not in roles:
        roles.append("isuper")
    return roles


def load_user(user_id: int) -> Optional[CurrentUser]:
    rows = DB.raw('SELECT * FROM "users" WHERE id = ?', [user_id]).result or []
    if not rows:
        return None
    row = rows[0]
    enable = row.get("enable")  # the column only exists when users.json has "enable"
    return CurrentUser(
        id=row["id"], name=row.get("name") or "", email=row.get("email") or "",
        enable=1 if enable is None else int(enable), roles=user_roles(row["id"]),
    )


async def get_current_user(credentials: Optional[HTTPAuthorizationCredentials] = Depends(_bearer)) -> CurrentUser:
    if credentials is None:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Not logged in")
    try:
        payload = jwt.decode(credentials.credentials, _secret(), algorithms=[JWT_ALGORITHM])
        user_id = int(payload["sub"])
    except (jwt.PyJWTError, KeyError, ValueError):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid token")
    user = load_user(user_id)
    if user is None:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="User not found")
    return user


async def get_current_active_user(user: CurrentUser = Depends(get_current_user)) -> CurrentUser:
    if user.enable == 0:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="This account has been disabled")
    return user


def require_role(*allowed: str):
    async def checker(user: CurrentUser = Depends(get_current_active_user)) -> CurrentUser:
        if not set(allowed) & set(user.roles):
            raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Not allowed")
        return user

    return checker


get_current_admin = require_role("isuper")
