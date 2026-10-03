"""FastAPI entrypoint for generated projects."""
from __future__ import annotations

import uvicorn
from fastapi import Depends, FastAPI

from app.api.auth import router as auth_router
from app.api.routers import all_routers  # written by `php setup.php` (python_set)
from app.core.auth import require_role

# isuper/islogin routers carry their own auth dependencies; ipublic/public need none.
BUILTIN_SCOPES = {"isuper", "islogin", "ipublic", "public"}


def create_app() -> FastAPI:
    app = FastAPI(title="Generated FastAPI Service")
    app.include_router(auth_router)
    for router in all_routers:
        scope = router.prefix.strip("/").split("/")[0]
        # custom roles from crud.roles (e.g. /executive/<model>): only users holding that role (or isuper)
        deps = [] if scope in BUILTIN_SCOPES else [Depends(require_role(scope, "isuper"))]
        app.include_router(router, dependencies=deps)
    return app


app = create_app()


if __name__ == "__main__":  # pragma: no cover
    uvicorn.run("app.main:app", host="0.0.0.0", port=8000, reload=True)
