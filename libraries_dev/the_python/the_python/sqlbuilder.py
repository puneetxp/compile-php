"""Tiny SQL string builder for generated proof-of-concept projects."""
from __future__ import annotations

from dataclasses import dataclass, field
from typing import Dict, List, Tuple

__all__ = ["SqlBuilder"]


@dataclass
class SqlBuilder:
    table: str
    fields: List[str] = field(default_factory=list)
    where_clauses: List[str] = field(default_factory=list)
    params: Dict[str, str] = field(default_factory=dict)

    def select(self, *columns: str) -> "SqlBuilder":
        if columns:
            self.fields = list(columns)
        return self

    def where(self, clause: str, **params: str) -> "SqlBuilder":
        self.where_clauses.append(clause)
        self.params.update(params)
        return self

    def build(self) -> Tuple[str, Dict[str, str]]:
        fields = ", ".join(self.fields) if self.fields else "*"
        sql = f"SELECT {fields} FROM {self.table}"
        if self.where_clauses:
            sql += " WHERE " + " AND ".join(self.where_clauses)
        return sql, self.params
