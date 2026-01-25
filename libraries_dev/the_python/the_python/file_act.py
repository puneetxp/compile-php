"""File utilities for working with uploads inside the generated app."""
from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path
from typing import BinaryIO, Optional

__all__ = ["FileAct"]


@dataclass
class FileAct:
    base_path: Path = Path("storage")

    def __post_init__(self) -> None:
        self.base_path.mkdir(parents=True, exist_ok=True)

    def save(self, file: BinaryIO, filename: str, directory: Optional[str] = None) -> Path:
        target_dir = self.base_path / directory if directory else self.base_path
        target_dir.mkdir(parents=True, exist_ok=True)
        destination = target_dir / filename
        with destination.open("wb") as fp:
            fp.write(file.read())
        return destination

    def delete(self, path: str | os.PathLike[str]) -> None:
        try:
            Path(path).unlink(missing_ok=True)
        except AttributeError:  # Python < 3.8
            file_path = Path(path)
            if file_path.exists():
                file_path.unlink()
