# AI Guidelines Overview (Monorepo Contract)

These files are intentionally split by concern for easier maintenance. Read and apply all of them, and keep each rule in the narrowest subject file that fits it.

## Rule Hierarchy
- Follow the strictest rule when guidance overlaps: security > data isolation > correctness > style.
- If instructions conflict or cannot both be satisfied, say so explicitly, explain the conflict, and choose the safest alternative.
- Never assume UI scoping is security. Server-side enforcement and validation are mandatory.

## Runtime Baseline
- Target PHP 8.4+ only.
- Use Filament v5 APIs.
- Assume long-lived workers. Avoid request-leaking static mutable state, prefer request-scoped or container-scoped state, and keep code safe under Laravel Octane.

## Verification Baseline
- Prefer per-package checks instead of repo-wide runs.
- When a guideline requires verification, run it if feasible. If not, say exactly what the user must run.

## Project References
- Issues are tracked in this repo's GitHub Issues. See `docs/agents/issue-tracker.md`.
- Use the canonical labels `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, and `wontfix`. See `docs/agents/triage-labels.md`.
- Treat this repo as multi-context: read `CONTEXT-MAP.md` first, then the relevant `CONTEXT.md` and ADRs. See `docs/agents/domain.md`.

## Package Contexts
- Every `packages/<pkg>` root must have a `CONTEXT.md`.
- Read the owning package's `CONTEXT.md` before code search or edits.
- Use the package context to route work quickly: identify the package role, search surface, related packages, and follow-up reads.
- `CONTEXT.md` is a routing document, not a full spec or changelog. Keep it short, stable, and easy to scan.
- Required frontmatter: `title`, `package`, `status`, `surface`, `family`.
- Standard section order:
  - `## Snapshot`
  - `## Read next`
  - `## Guardrails`
- `Snapshot` should name the Composer package, the package role, the best starting search paths, and the related packages.
- `Read next` should point to the package docs in this order: `01-overview`, `03-configuration`, `04-usage`, `99-troubleshooting`, then `02-installation` when setup or publishing is involved. Add sibling `CONTEXT.md` files when cross-package changes are likely.
- `Guardrails` should state the package's ownership boundary, the main surfaces it owns, what belongs in sibling packages, and any must-follow review rule such as revalidating IDs or updating docs in the same pass.
- `filament-*` packages are adapters, not domain owners.
- If a task crosses core and Filament boundaries, read both contexts before editing.
- Package docs under `docs/*.md` are canonical. When public behavior or config changes, update the owning package's docs in the same pass.
