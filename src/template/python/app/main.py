"""FastAPI entrypoint for generated projects."""
from __future__ import annotations

import uvicorn
from fastapi import FastAPI

from app.api import all_routers


def create_app() -> FastAPI:
    app = FastAPI(title="Generated FastAPI Service")
    for router in all_routers:
        app.include_router(router)
    return app


app = create_app()


if __name__ == "__main__":  # pragma: no cover
    uvicorn.run("app.main:app", host="0.0.0.0", port=8000, reload=True)
