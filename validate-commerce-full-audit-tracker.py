#!/usr/bin/env python3
from __future__ import annotations

import argparse
import fnmatch
import sys
from pathlib import Path
from typing import Any

import yaml

ACTIVE = {"claimed", "in_progress", "review"}
ALLOWED = {"todo", "claimed", "in_progress", "blocked", "review", "done"}


def load(path: str | Path) -> dict[str, Any]:
    with Path(path).open(encoding="utf-8") as handle:
        data = yaml.safe_load(handle)
    if not isinstance(data, dict):
        raise ValueError(f"Tracker {path} must contain a YAML mapping at its root.")
    return data


def task_paths(task: dict[str, Any]) -> list[str]:
    scope = task.get("exclusive_scope", {})
    if isinstance(scope, list):
        return [value for value in scope if isinstance(value, str)]
    if not isinstance(scope, dict):
        return []

    result: list[str] = []
    for key in ("existing", "new", "documentation"):
        values = scope.get(key, [])
        if isinstance(values, list):
            result.extend(value for value in values if isinstance(value, str))
    return result


def scopes_overlap(left: str, right: str) -> bool:
    left = left.replace("\\", "/")
    right = right.replace("\\", "/")
    return (
        left == right
        or fnmatch.fnmatch(left, right)
        or fnmatch.fnmatch(right, left)
        or (left.endswith("/**") and right.startswith(left[:-3]))
        or (right.endswith("/**") and left.startswith(right[:-3]))
    )


def dependency_ancestors(task_id: str, by_id: dict[str, dict[str, Any]]) -> set[str]:
    ancestors: set[str] = set()
    pending = list(by_id[task_id].get("depends_on", []))
    while pending:
        current = pending.pop()
        if current in ancestors or current not in by_id:
            continue
        ancestors.add(current)
        pending.extend(by_id[current].get("depends_on", []))
    return ancestors


def validate_tracker(
    data: dict[str, Any],
    label: str,
    errors: list[str],
    *,
    check_future_scope_order: bool = False,
) -> tuple[list[dict[str, Any]], dict[str, dict[str, Any]]]:
    tasks = data.get("tasks", [])
    if not isinstance(tasks, list):
        errors.append(f"{label}: tasks must be a list")
        return [], {}

    ids = [task.get("id") for task in tasks if isinstance(task, dict)]
    if len(ids) != len(tasks):
        errors.append(f"{label}: every task must be a mapping with an id")
    if len(ids) != len(set(ids)):
        errors.append(f"{label}: duplicate task IDs")

    by_id = {
        task["id"]: task
        for task in tasks
        if isinstance(task, dict) and isinstance(task.get("id"), str)
    }

    for task_id, task in by_id.items():
        status = task.get("status")
        if status not in ALLOWED:
            errors.append(f"{label}:{task_id}: invalid status {status!r}")

        dependencies = task.get("depends_on", [])
        if not isinstance(dependencies, list):
            errors.append(f"{label}:{task_id}: depends_on must be a list")
            dependencies = []

        for dependency in dependencies:
            if dependency not in by_id:
                errors.append(f"{label}:{task_id}: missing dependency {dependency}")

        prior_dependencies = task.get("prior_dependencies", [])
        if label == "new" and not isinstance(prior_dependencies, list):
            errors.append(f"{label}:{task_id}: prior_dependencies must be a list")

        if status in ACTIVE:
            if not task.get("owner") or not task.get("branch") or not task.get("claimed_at"):
                errors.append(f"{label}:{task_id}: active without owner, branch, and claimed_at")
            for dependency in dependencies:
                if dependency in by_id and by_id[dependency].get("status") != "done":
                    errors.append(
                        f"{label}:{task_id}: active before dependency {dependency} is done"
                    )

        if status == "done":
            acceptance = task.get("acceptance", [])
            if not isinstance(acceptance, list) or any(
                not isinstance(item, dict) or not item.get("done") for item in acceptance
            ):
                errors.append(f"{label}:{task_id}: done with unchecked acceptance")
            evidence = task.get("evidence", {})
            if not isinstance(evidence, dict):
                evidence = {}
            for key in ("commit", "tests", "static_analysis", "reviewer", "review_notes"):
                if not evidence.get(key):
                    errors.append(f"{label}:{task_id}: done missing evidence.{key}")

    visiting: set[str] = set()
    visited: set[str] = set()

    def visit(task_id: str) -> None:
        if task_id in visiting:
            errors.append(f"{label}: dependency cycle at {task_id}")
            return
        if task_id in visited:
            return
        visiting.add(task_id)
        for dependency in by_id[task_id].get("depends_on", []):
            if dependency in by_id:
                visit(dependency)
        visiting.remove(task_id)
        visited.add(task_id)

    for task_id in by_id:
        visit(task_id)

    active = [task for task in by_id.values() if task.get("status") in ACTIVE]
    for index, left in enumerate(active):
        for right in active[index + 1 :]:
            for left_path in task_paths(left):
                for right_path in task_paths(right):
                    if scopes_overlap(left_path, right_path):
                        errors.append(
                            f"{label}: active overlap {left['id']} <-> {right['id']}: "
                            f"{left_path} / {right_path}"
                        )

    if check_future_scope_order:
        ancestors = {task_id: dependency_ancestors(task_id, by_id) for task_id in by_id}
        ordered_tasks = list(by_id.values())
        for index, left in enumerate(ordered_tasks):
            for right in ordered_tasks[index + 1 :]:
                overlaps = [
                    (left_path, right_path)
                    for left_path in task_paths(left)
                    for right_path in task_paths(right)
                    if scopes_overlap(left_path, right_path)
                ]
                if not overlaps:
                    continue
                if left["id"] in ancestors[right["id"]] or right["id"] in ancestors[left["id"]]:
                    continue
                left_path, right_path = overlaps[0]
                errors.append(
                    f"{label}: unordered future scope overlap {left['id']} <-> {right['id']}: "
                    f"{left_path} / {right_path}"
                )

    return active, by_id


def validate_cross_tracker(
    current: dict[str, Any],
    prior: dict[str, Any],
    current_active: list[dict[str, Any]],
    prior_active: list[dict[str, Any]],
    current_by_id: dict[str, dict[str, Any]],
    prior_by_id: dict[str, dict[str, Any]],
    errors: list[str],
) -> None:
    for task_id, task in current_by_id.items():
        required = task.get("prior_dependencies", [])
        if not isinstance(required, list):
            continue
        for dependency in required:
            if dependency not in prior_by_id:
                errors.append(f"new:{task_id}: missing prior dependency {dependency}")
            elif task.get("status") in ACTIVE and prior_by_id[dependency].get("status") != "done":
                errors.append(
                    f"new:{task_id}: active before prior dependency {dependency} is done"
                )

    # Every static overlap with the prior handoff must be explicitly recorded.
    for current_task in current_by_id.values():
        declared = set(current_task.get("prior_dependencies", []))
        for prior_task in prior_by_id.values():
            overlap = next(
                (
                    (current_path, prior_path)
                    for current_path in task_paths(current_task)
                    for prior_path in task_paths(prior_task)
                    if scopes_overlap(current_path, prior_path)
                ),
                None,
            )
            if overlap is None:
                continue
            if prior_task["id"] not in declared:
                errors.append(
                    f"new:{current_task['id']}: static overlap with prior {prior_task['id']} "
                    f"is not declared in prior_dependencies: {overlap[0]} / {overlap[1]}"
                )

    for current_task in current_active:
        for prior_task in prior_active:
            for current_path in task_paths(current_task):
                for prior_path in task_paths(prior_task):
                    if scopes_overlap(current_path, prior_path):
                        errors.append(
                            f"cross-tracker active overlap {current_task['id']} <-> "
                            f"{prior_task['id']}: {current_path} / {prior_path}"
                        )


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Validate the new-findings tracker and the prior architecture tracker."
    )
    parser.add_argument("tracker")
    parser.add_argument("--prior-tracker")
    args = parser.parse_args()

    errors: list[str] = []
    current = load(args.tracker)
    current_active, current_by_id = validate_tracker(
        current, "new", errors, check_future_scope_order=True
    )

    if args.prior_tracker:
        prior = load(args.prior_tracker)
        prior_active, prior_by_id = validate_tracker(prior, "prior", errors)
        validate_cross_tracker(
            current,
            prior,
            current_active,
            prior_active,
            current_by_id,
            prior_by_id,
            errors,
        )
    else:
        if current_active:
            errors.append("new: active tasks require --prior-tracker")
        print(
            "WARNING: prior tracker not supplied; prior dependencies and cross-handoff overlap were not checked.",
            file=sys.stderr,
        )

    if errors:
        print("\n".join(f"ERROR: {error}" for error in errors))
        return 1

    print(
        f"OK: {len(current.get('tasks', []))} new tasks; dependency graph valid; "
        "all new overlaps are ordered; all prior overlaps are declared; no active overlap detected."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
