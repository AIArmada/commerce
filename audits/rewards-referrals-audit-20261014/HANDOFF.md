# Handoff — Rewards/Referrals audit → implement F1–F9 severe fixes

Date: 2026-10-14. Prepared so a fresh agent can continue without re-doing the audit.

## Next session focus

Implement the verified F1–F9 severe fixes from the fix plan (reference below),
test-first, then independent review, then final parent verification. The audit
itself is **done** — do not re-audit; only re-inspect a cited code body when you
are about to fix that claim.

## Where things stand (verified 2026-10-14)

- Workspace: `/Users/Saiffil/Herd/commerce`, branch `main`, HEAD `e4cded62f`,
  **working tree clean, zero diff**. Nothing was implemented, committed, or
  partially edited. There is no partial work to preserve or untangle.
- Scope that was audited: `packages/rewards`, `packages/filament-rewards`,
  `packages/referrals`, `packages/filament-referrals`, plus maintained tests
  under `tests/src/{Rewards,FilamentRewards,Referrals,FilamentReferrals}`.
  `commerce-support` was read-only contract reference. The ilmu360 host app was
  never in scope and must stay untouched in the fix pass.
- Origin of the task: G1 in
  `/Users/Saiffil/Herd/ilmu360/docs/share-rewards-gaps-and-closure.md` claims
  Loop A (earn for own activity without a share link) is unimplemented.
  Conclusion reached and corroborated twice in code: the host reader is only
  the first gate; the load-bearing weakness is in the commerce `rewards`
  package, which rejects unattributed facts for **every** role
  (`packages/rewards/src/Support/RewardEvaluationCandidates.php:38`,
  `packages/rewards/src/Support/RewardPreviewEngine.php:138`). This is fix
  group F3 in the plan.

## Authoritative artifacts (read these, do not recreate them)

All in this directory, `audits/rewards-referrals-audit-20261014/` in the
commerce workspace (audit reports + plan: 1,747 lines; moved here from the OS
temp directory on 2026-10-14 so they survive a restart — still uncommitted
unless the user commits them):

- `fix-plan.md` (711 lines) — **the** implementation spec: F1–F9 in dependency
  order, exact files/symbols, behavior contracts, red-test-first instructions,
  docs to update per group, REPORT-ONLY list with blockers (lines 630–694),
  final verification commands (lines 696–705).
- `rewards-core.md`, `filament-rewards.md`, `referrals-core.md`,
  `filament-referrals.md`, `cross-package.md` — per-package audit evidence
  behind the plan (claims, failure scenarios, false positives).

Fix groups at a glance (details only in `fix-plan.md`): F1 owner-context for
rewards jobs/sweep · F2 `RewardProgramGuard` coverage for six write actions ·
F3 role-scoped attribution + Filament preview · F4 referrals global-write
guard + per-owner expire · F5 side-scoped atomic points rail (double-mint,
orphan) · F6 fail-closed points reversal · F7 single-use beneficiary-bound
vouchers · F8 Filament rewards staff/Gate enforcement · F9 core
`UpdateReferralProgram` action, Edit delegates, no status-via-edit.

## Settled decisions (do not re-open)

- **Fix scope (user-chosen):** audit all four packages and fix **all verified
  severe (critical/high) findings**. REPORT-ONLY items in the plan stay out
  unless the user explicitly re-authorizes each one (several need product or
  host-contract decisions, e.g. XP-06 platform-program participation).
- **Attribution semantics:** actor-bound roles (Participant/Purchaser/Staff)
  may earn from unattributed facts; sharer-bound roles (Recipient/Sharer)
  still require attribution. Single predicate lives on the role enum per F3.
- **Commerce first:** package seam lands and is verified in this repo before
  anyone touches ilmu360's host reader/dispatch (the remaining host-side G1
  work is a later, separate step).
- **Execution contract per group:** red Pest regression test first (maintained
  tests under `tests/src/<Pkg>`), smallest root-cause fix, docs in the same
  pass, then per-package gates. Repo rules in `AGENTS.md` / `.ai/rules` and
  each package `CONTEXT.md` govern (owner boundary, no DB FK
  constraints/`down()`, migrations evolve in place, Pest always `--parallel`,
  PHPStan per touched package `--level=6`, Pint changed files only).
- Dependency order matters: F1 before F2/F3; F5 needs F1; F6 needs F1+F5;
  F9 needs F5. Suggested writer slices: (1) rewards core F1–F3 core parts,
  (2) referrals core F4–F7, (3) adapters F3-filament + F8 + F9.

## Why implementation has not started — writer model routing (important)

The user required a specific model to write/review the code. Every requested
route failed **before any child ran a single tool**, from this parent session
(muse-wonder-full-1.0 bucket):

- `gpt-6-luna` (max effort) → `workflow_child_route_model_unknown`.
- `muse-spark-1.3-contributor` (max effort) → `workflow_child_route_model_unknown`.
- `muse-spark-1.3` (max effort, and again with no effort override in a
  dedicated no-tool probe) → `workflow_child_route_model_cross_bucket`
  ("Choose a visible model and supported effort in the admitted parent
  provider/profile route").

So: not an effort problem — those models are not selectable as workflow
children from this session's provider/profile bucket. Twice offered the
visible default route as replacement, the user instead chose "try
muse-spark-1.3", then **"Stop at audit and plan"**. Therefore:

- Do **not** silently substitute a different writer model. Before editing,
  confirm with the user which writer route to use (a model visible in the new
  session's bucket, or explicit approval for the default parent route).
- Do not trust any earlier chat line claiming writers "are running" on
  muse-spark — they were configured but rejected at routing; **zero writer,
  reviewer, or repair evidence exists**. There are no `writer-*.md`,
  `reviewer.md`, or `rereviewer.md` handoffs, and no retained workflow writer
  results to reuse.
- For reference only, the last implementation workflow script (three writer
  slices + reviewer + repair + synthesis, model constant near the top) is at
  `/Users/Saiffil/.local/share/muse/sessions/2026/10/14/01a136c7-a4e8-7523-96c8-6e835cd5fb28/workflows/scripts/01a13734-0ea8-7550-854f-ce86cec6ca4e.js`.
  It can be adapted once a routable model is confirmed; its synthesis child
  also worked, but note it once mislabeled the triage author as a reviewer —
  there is **no** reviewer pass on record.

## Verification: observed vs never observed

Observed during the audit (in the reports, pre-fix baseline):

- `./vendor/bin/pest --parallel tests/src/Referrals` — 21 passed.
- `./vendor/bin/pest --parallel tests/src/FilamentReferrals` — 8 passed.
- Probes reproducing: points double-mint (2 awards after first side, 4 after
  second, vs 2 promised) and null-owner rewards job failure
  (`NoCurrentOwnerException`).

Never observed (do not claim these until you run them): any post-fix Pest run,
rewards/filament-rewards suites, PHPStan, Pint, independent review verdict.
Final gates are listed verbatim in `fix-plan.md:696-705`; after the reviewer
passes, the parent agent must still re-run per-package Pest (`--parallel`),
PHPStan level 6 on touched `src`, Pint on changed files, and the two `rg`
invariant greps, and review the full diff before reporting.

## Suggested skills for the next agent

- `bundled:workflow-authoring` — if orchestrating delegated writers/reviewer
  again (load once before scripting).
- `bundled:durable-test-collateral` — before the first implementation edit.
- `pest-testing`, `laravel-actions`, `laravel-best-practices`,
  `spatie-laravel-php` — before writing tests/Actions/PHP. Where generic skill
  defaults conflict with this repo's `AGENTS.md` rules, the repo rules win.

## First actions for the next agent

1. Confirm the tree is still clean at `e4cded62f` (or record the new baseline).
2. Read `fix-plan.md` global rules (lines 20–33) and F1 in full; skim the
   REPORT-ONLY list so you don't implement blocked items.
3. Confirm the writer/reviewer model route with the user (see routing section).
4. Implement slice by slice in dependency order, test-first, keeping a
   per-slice handoff (red evidence, files, exact gate output) alongside the
   audit files.
5. Independent review of the whole diff → repair loop → parent final gates.
6. Report per F-group: fixed with proof, or unresolved with the concrete
   blocker — and restate that the ilmu360 host-side reader change remains a
   separate follow-up after the commerce seam lands.
