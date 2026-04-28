# General Guidelines (Monorepo-Specific)

Use this file for cross-cutting guidance that is **not already covered** in other guideline files.

## 1) Workflow Quality
- For non-trivial work (multi-step changes, architecture decisions, or risk of regressions), write a brief plan before coding.
- If new evidence invalidates the plan, stop and re-plan.
- Verify behavior before declaring done.

## 2) Runtime-Extension Safety
Before removing a method that static analysis reports as undefined, verify runtime extension sources first:
- `macro()` / `hasMacro()` registrations
- package mixins / traits
- framework/plugin runtime extension points

If runtime-provided, preserve behavior and fix analysis with a narrow, targeted approach rather than deleting feature calls.

## 3) Action-Oriented Orchestration
- Prefer Laravel Actions for reusable orchestration that spans transactions, side effects, normalization, or multiple entrypoints.
- Keep trivial single-step handlers inline when extraction adds no clarity.
- Reuse existing actions before creating new ones.

## 4) Tracking Review for Behavioral UI Changes
When a task changes user behavior (entry points, forms, actions, or meaningful workflow transitions), evaluate whether product tracking should be updated.
- Prefer high-signal events over noisy click logs.
- Prefer server-confirmed events for backend outcomes.
