# General Guidelines (Monorepo-Specific)

Use this file for cross-cutting guidance that is **not already covered** in other guideline files.

## 1) Workflow Quality
- For non-trivial work (multi-step changes, architecture decisions, or risk of regressions), write a brief plan before coding.
- If new evidence invalidates the plan, stop and re-plan.
- Verify behavior before declaring done.

### Codebase-aware vs architecture-first
- Start **codebase-aware** by default: inspect sibling files, follow established conventions, and prefer the smallest change that fits the current package and boundary.
- Steer **architecture-first** when copying the existing pattern would spread a known design problem, duplicate shared logic across packages, or force a fix that is locally correct but systemically wrong.
- Common escalation signals:
  - the root cause lives in a shared primitive, package boundary, owner-scoping rule, or cross-cutting contract;
  - the same workaround would need to be repeated in multiple files or packages;
  - the local pattern conflicts with hard rules such as security, multitenancy, Octane safety, or package independence;
  - the right fix likely belongs in `commerce-support` or another shared foundation, not in one package-specific patch.
- When you switch, say so explicitly: name the local pattern you are not copying, explain why, propose the smallest architecture change that fixes the root cause, and list the surfaces that need verification.
- Stay architecture-first in scope, not in blast radius: prefer one well-placed shared primitive or boundary correction over a broad rewrite.
- During refactors and reviews, look for opportunities to preserve or add extension seams—hooks, domain events, metadata, contracts, resolvers, and support classes—so the package stays easy to extend without hard-coded branching.

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
- During refactors, reviews, or audits, look for repeated orchestration that should become reusable Actions, Services, or Use Cases instead of living in controllers, jobs, or UI handlers.

## 4) Tracking Review for Behavioral UI Changes
When a task changes user behavior (entry points, forms, actions, or meaningful workflow transitions), evaluate whether product tracking should be updated.
- Prefer high-signal events over noisy click logs.
- Prefer server-confirmed events for backend outcomes.

## 5) Thoughtfulness Before Changes

### Before coding
- State assumptions explicitly.
- If there are multiple interpretations, name them and ask instead of guessing.
- Surface tradeoffs and push back when a request is unclear or overcomplicated.

### Keep it simple
- Use the smallest correct change.
- Do not add speculative abstractions, configurability, error handling for impossible cases, or features beyond the request.
- If a 50-line fix is enough, do not write 200.
- Prefer the simplest solution a senior engineer would not call overengineered.

### Be surgical
- Touch only what the request requires.
- Match existing style; do not refactor adjacent code, comments, or formatting.
- Clean up only your own mess.
- Mention unrelated dead code instead of deleting it.
- Remove only imports, variables, or functions your change makes unused.

### Work toward proof
- Write a brief plan for multi-step work.
- Define success criteria up front.
- Verify each step until the result is done.
- Turn tasks into tests or checks when possible:
  - Add validation → write failing tests first, then make them pass.
  - Fix a bug → reproduce it with a test, then fix it.
  - Refactor X → verify behavior before and after.
- Every changed line should trace directly to the request.
