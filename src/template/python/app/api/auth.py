"""Login / register / status routes (mirrors /login, /register of the PHP and Deno runtimes)."""

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel

from app.core.auth import CurrentUser, create_access_token, get_current_active_user, hash_password, load_user
from app.core.db import DB

router = APIRouter(tags=["auth"])


class LoginInput(BaseModel):
    email: str
    password: str


class RegisterInput(LoginInput):
    name: str


def _login_response(user: CurrentUser) -> dict:
    return {"token": create_access_token(user.id), "id": user.id, "name": user.name, "email": user.email, "roles": user.roles}


@router.post("/login")
def login(body: LoginInput):
    rows = DB.raw('SELECT id, password FROM "users" WHERE email = ?', [body.email]).result or []
    if not rows:
        raise HTTPException(status_code=404, detail="User Not Found")
    if rows[0]["password"] != hash_password(body.password):
        raise HTTPException(status_code=401, detail="Password Not Correct")
    user = load_user(rows[0]["id"])
    if user.enable == 0:
        raise HTTPException(status_code=403, detail="This account has been disabled")
    return _login_response(user)


@router.post("/register")
def register(body: RegisterInput):
    if DB.raw('SELECT 1 FROM "users" WHERE email = ?', [body.email]).result:
        raise HTTPException(status_code=422, detail={"email": "Email Already Taken"})
    rows = DB.raw(
        'INSERT INTO "users" ("name", "email", "password") VALUES (?, ?, ?) RETURNING id',
        [body.name, body.email, hash_password(body.password)],
    ).result
    return _login_response(load_user(rows[0]["id"]))


@router.get("/login")
def status(user: CurrentUser = Depends(get_current_active_user)):
    return {"id": user.id, "name": user.name, "email": user.email, "roles": user.roles}
