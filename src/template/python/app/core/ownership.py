"""
Row ownership for the /islogin/* role controllers (used by app/core/crud_service.py).

A signed-in user only lists, reads, updates and deletes rows their table's rule matches, and can
only create rows attached to parents they own. Admins use /isuper/*, which is never scoped.

Default: a table with a `user_id` column is scoped to `t.user_id = <signed-in user>`, and `user_id`
is forced to the signed-in user on create. Add explicit rules below for anything else.
Rules are SQL conditions on alias `t`; `{uid}` is the signed-in user's integer id.

Example (from a project where plots belong to farms):
    _FARMS = "SELECT id FROM farms WHERE user_id = {uid}"
    OWNERSHIP = {"farms": "t.user_id = {uid}", "farm_plots": f"t.farm_id IN ({_FARMS})"}
    PARENTS = {"farm_plots": {"farm_id": "farms"}}
"""

from typing import Dict, List, Optional

# table -> SQL rule on alias t
OWNERSHIP: Dict[str, str] = {
    "users": "t.id = {uid}",
}

# Tables every signed-in user may read (writes are still scoped).
SHARED_READ = set()

# On create, these columns are set to the signed-in user whatever the client sent.
OWNER_COLUMNS: Dict[str, List[str]] = {}

# On create/update, these parent ids must point at something the user owns (checked with the parent's rule).
PARENTS: Dict[str, Dict[str, str]] = {}

DEFAULT_OWNER_COLUMN = "user_id"


def owner_condition(table: str, uid: int, columns: Optional[List[str]] = None) -> Optional[str]:
    """SQL condition limiting `t` to rows the user owns, or None if the table isn't owner-scoped."""
    rule = OWNERSHIP.get(table)
    if not rule and columns is not None and DEFAULT_OWNER_COLUMN in columns:
        rule = f"t.{DEFAULT_OWNER_COLUMN} = {{uid}}"
    return f"({rule.format(uid=int(uid))})" if rule else None


def owner_columns(table: str, columns: List[str]) -> List[str]:
    """Columns forced to the signed-in user on create (and never changeable on update)."""
    if table in OWNER_COLUMNS:
        return [c for c in OWNER_COLUMNS[table] if c in columns]
    if table not in OWNERSHIP and DEFAULT_OWNER_COLUMN in columns:
        return [DEFAULT_OWNER_COLUMN]
    return []


def is_scoped(table: str, columns: Optional[List[str]] = None) -> bool:
    return table in OWNERSHIP or (columns is not None and DEFAULT_OWNER_COLUMN in columns)
