# Start Here — Commerce Full Audit, New Findings Only

This handoff contains only findings that are distinct from the previous architecture handoff.

## Files every coding agent must read

1. `commerce-full-audit-new-findings-20260713.html`
   - Read the complete finding assigned to the task.
   - This is the human-readable evidence, root cause, impact, and required target state.

2. `commerce-full-audit-tracker-20260713.yaml`
   - This is the authoritative execution contract and progress tracker.
   - Read the global `agent_protocol` and the complete assigned task.
   - Do not work from the Markdown checklist alone.

3. The repository's current `architecture-execution-tracker-20260712.yaml`
   - This file comes from the previous handoff and must be the latest working copy, not a stale copy bundled elsewhere.
   - The new tracker contains `prior_dependencies` for files shared with previous work.

## Before claiming a task

From the repository root, run:

```bash
python3 validate-commerce-full-audit-tracker.py \
  commerce-full-audit-tracker-20260713.yaml \
  --prior-tracker architecture-execution-tracker-20260712.yaml
```

Then:

1. Confirm every `depends_on` task is `done` in the new tracker.
2. Confirm every `prior_dependencies` task is `done` in the previous tracker.
3. Confirm the validator reports no active or future-unordered scope overlap.
4. Set `owner`, `branch`, `claimed_at`, and `status: claimed` in one tracker-only commit.
5. Run the validator again.
6. Only then edit repository source.

## Non-negotiable implementation rule

Implement the target design directly. Do not preserve compatibility aliases, dual reads, dual writes, deprecated commands, fallback branches, legacy schema columns, or old implementation tests.

## File purposes

- `commerce-full-audit-new-findings-20260713.html` — primary report for humans and agents.
- `commerce-full-audit-new-findings-20260713.md` — diffable text version of the report.
- `commerce-full-audit-tracker-20260713.yaml` — source of truth for ownership, dependencies, exact scope, instructions, acceptance, and evidence.
- `commerce-full-audit-checklist-20260713.md` — human progress view only.
- `commerce-prior-exclusion-ledger-20260713.md` — root causes intentionally omitted because previous agents own them.
- `commerce-package-coverage-20260713.md` — evidence that all 63 packages were included and the disposition for each.
- `validate-commerce-full-audit-tracker.py` — validates task graph, current scope ordering, declared prior overlaps, and active ownership.
- `evidence/` — audit-time static scan and syntax evidence. It helps reviewers reproduce reasoning but does not replace direct source inspection.

## Completion rule

A task is not `done` until all acceptance items are checked and the YAML contains the commit SHA, exact tests, static-analysis output, independent reviewer, and review notes. If required verification cannot run, mark the task `blocked`; never claim success from inference.
