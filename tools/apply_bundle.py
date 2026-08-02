from __future__ import annotations

import base64
import io
import tarfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
parts = sorted((ROOT / "tools").glob("bundle.part*"))
if not parts:
    raise SystemExit("No bundle parts found")

encoded = "".join(part.read_text(encoding="utf-8").strip() for part in parts)
payload = base64.b64decode(encoded, validate=True)

with tarfile.open(fileobj=io.BytesIO(payload), mode="r:gz") as archive:
    root_resolved = ROOT.resolve()
    for member in archive.getmembers():
        destination = (ROOT / member.name).resolve()
        if destination != root_resolved and root_resolved not in destination.parents:
            raise RuntimeError(f"Unsafe archive path: {member.name}")
    archive.extractall(ROOT)

print(f"Applied {len(parts)} bundle parts to {ROOT}")
