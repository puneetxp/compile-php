# The Python Library

FastAPI-first helper library inspired by `the_lib`, `the_deno`, and the Go/.NET ports. It provides lightweight building blocks that the generator can re-use when scaffolding Python services.

## Features

- **ModelService**: in-memory data store with a CRUD friendly API. Works without a database so the generated FastAPI app can boot immediately.
- **SqlBuilder**: tiny helper to compose SELECT queries in case you plug a real database later.
- **Response helpers**: opinionated wrappers for consistent API responses and HTTP exceptions.
- **Auth**: password hashing & verification utilities using `bcrypt` compatible hashes.
- **Session**: super small session registry for prototyping.
- **FileAct**: helpers for saving uploads inside `storage/`.
- **Mail**: SMTP wrapper that reads credentials from env vars.

## Usage

```python
from the_python.model import ModelService

class UserModel(ModelService):
    def __init__(self):
        super().__init__(table="users")

service = UserModel()
service.create({"name": "Cascade"})
print(service.all())
```

All modules are pure Python and can be installed with `pip install -e libraries_dev/the_python` or imported by tweaking `sys.path` (the generator injects it automatically).
