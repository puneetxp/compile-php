"""Utility helpers for the generated FastAPI application."""

from .model import ModelService
from .response import (
    success_response,
    error_response,
    not_found_response,
)
from .auth import AuthService
from .session import session_store
from .file_act import FileAct
from .mail import Mailer
from .sqlbuilder import SqlBuilder

__all__ = [
    "ModelService",
    "success_response",
    "error_response",
    "not_found_response",
    "AuthService",
    "session_store",
    "FileAct",
    "Mailer",
    "SqlBuilder",
]

__version__ = "0.1.0"
