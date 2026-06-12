# General Guidelines (Monorepo-Specific)

Use this file for cross-cutting judgment, planning, and change execution.

## Plan Before Coding
- For non-trivial work such as multi-step changes, architecture decisions, or risky edits, write a brief plan before coding.
- If new evidence invalidates the plan, stop and re-plan.
- State assumptions explicitly. If there are multiple interpretations, name them and ask instead of guessing.
- Push back when a request is unclear, internally inconsistent, or overcomplicated.

## Choose the Right Shape of Change
- Start codebase-aware by default: inspect sibling files, follow established conventions, and prefer the smallest change that fits the package boundary.
- Switch to architecture-first when copying the existing pattern would spread a known design problem, duplicate shared logic across packages, or create a fix that is locally correct but systemically wrong.
- When you switch, say so explicitly: name the local pattern you are not copying, explain why, propose the smallest shared correction, and list the surfaces that need verification.
- Stay architecture-first in scope, not in blast radius: prefer one well-placed shared primitive or boundary correction over a broad rewrite.
- Preserve extension seams where they help the codebase stay adaptable: hooks, domain events, metadata, contracts, resolvers, and support classes.

## Keep the Change Surgical
- Use the smallest correct change.
- Do not add speculative abstractions, configurability, or error handling for impossible cases.
- If a 50-line fix is enough, do not write 200.
- Match existing style; do not refactor adjacent code, comments, or formatting.
- Clean up only your own mess.
- Mention unrelated dead code instead of deleting it.
- Remove only imports, variables, or functions your change makes unused.
- Never "cleanup" or mass-revert without permission.

## Runtime Extension Safety
- Before removing a method that static analysis reports as undefined, verify runtime extension sources first.
- Check `macro()` / `hasMacro()` registrations, package mixins or traits, and framework or plugin extension points.
- If the method is runtime-provided, preserve behavior and fix analysis with a narrow, targeted change.

## Reusable Orchestration
- Prefer Laravel Actions for reusable orchestration that spans transactions, side effects, normalization, or multiple entry points.
- Keep trivial single-step handlers inline when extraction adds no clarity.
- Reuse existing Actions before creating new ones.

## Behavioral Changes
- When a task changes user behavior such as entry points, forms, actions, or meaningful workflow transitions, evaluate whether product tracking should be updated.
- Prefer high-signal events over noisy click logs.
- Prefer server-confirmed events for backend outcomes.

## Proof
- Verify behavior before declaring done.
- Write a brief success criterion for multi-step work.
- Turn tasks into tests or checks when possible:
  - Add validation -> write failing tests first, then make them pass.
  - Fix a bug -> reproduce it with a test, then fix it.
  - Refactor -> verify behavior before and after.
- Every changed line should trace directly to the request.
