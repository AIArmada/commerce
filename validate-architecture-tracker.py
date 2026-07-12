#!/usr/bin/env python3
from __future__ import annotations
import sys, yaml
from pathlib import Path
from collections import defaultdict, deque

path = Path(sys.argv[1] if len(sys.argv) > 1 else "architecture-execution-tracker-20260712.yaml")
data = yaml.safe_load(path.read_text())
tasks = data["tasks"]
ids = {t["id"] for t in tasks}
errors = []

for t in tasks:
    if t["status"] not in data["tracker_policy"]["allowed_statuses"]:
        errors.append(f"{t['id']}: invalid status {t['status']}")
    for dep in t.get("depends_on", []):
        if dep not in ids:
            errors.append(f"{t['id']}: missing dependency {dep}")
    if t["status"] in {"claimed","in_progress","review"}:
        if not t.get("owner") or not t.get("branch") or not t.get("claimed_at"):
            errors.append(f"{t['id']}: active task missing owner/branch/claimed_at")
        if not t.get("exclusive_scope"):
            errors.append(f"{t['id']}: active task has empty scope")
        for p in t.get("exclusive_scope", []):
            if "*" in p or " only" in p or "excluding" in p:
                errors.append(f"{t['id']}: non-exact active scope: {p}")

# DAG
indeg = {i: 0 for i in ids}
graph = defaultdict(list)
for t in tasks:
    for dep in t.get("depends_on", []):
        graph[dep].append(t["id"])
        indeg[t["id"]] += 1
q = deque([i for i,d in indeg.items() if d == 0])
seen = 0
while q:
    n = q.popleft(); seen += 1
    for m in graph[n]:
        indeg[m] -= 1
        if indeg[m] == 0: q.append(m)
if seen != len(ids):
    errors.append("dependency cycle detected")

# Active overlaps
owners = defaultdict(list)
for t in tasks:
    if t["status"] in {"claimed","in_progress","review"}:
        for p in t.get("exclusive_scope", []):
            owners[p].append(t["id"])
for p, ts in owners.items():
    if len(ts) > 1:
        errors.append(f"active overlap {p}: {', '.join(ts)}")

if errors:
    print("INVALID")
    for e in errors: print("-", e)
    raise SystemExit(1)
print(f"VALID: {len(tasks)} tasks, acyclic dependencies, no active overlap")
