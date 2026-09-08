# growth Audit — DONE (2026-09-08)

## Verdict

The experimentation package (`growth` + `filament-growth`) has passed
full review and implementation. Scope helper documented as a thin
delegator, god actions split into builders + pure calculator,
lifecycle transitions centralized, adapters on core services — with
zero rated findings remaining.

## What was done

- **Scope delegator (A2):** kept as documented thin delegator — 11
  active call sites (not 8) with mixed owner configs make removal
  unsafe; behavior unchanged — see `code-fixes-record.md`.
- **Action split (A3):** pure query builders + `MetricsCalculator`;
  actions thin orchestrators with signatures intact — see
  `code-fixes-record.md`.
- **Transitions (Q1):** `Experiment::transitionTo()` centralizes
  status→timestamp mapping; archive command + UI routed through it —
  see `code-fixes-record.md`.
- **Adapters (F1):** core services/calculator called; domain
  owner-count scope; no hardcoded currency, no duplicated math — see
  `code-fixes-record.md`.
- **Falsified:** assignment-mutation gap (no such actions exist;
  paths already revalidate) — see `code-fixes-record.md`.
- Suites: Growth 144 passed (452 assertions), FilamentGrowth 59
  passed (204 assertions); PHPStan level 6 clean (32 + 23 files).
  No migration required.

## Residual notes

- Dashboard batching deferred (`GrowthStatsAggregator` still
  per-experiment); the ≤3-query test for 10 experiments is not
  claimed — revisit with measured pain.
- Projection-action decomposition deferred; metric math deduplicated
  already.
- Transition notes unused (no notes column) — accepted signature
  compat with the audit trait.

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
