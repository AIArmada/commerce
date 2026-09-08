# signals Audit — DONE (2026-09-08)

## Verdict

The behavioral-analytics package (`signals` + `filament-signals`) has
passed full review and implementation. The god recorder is split into
per-source recorders with fail-loud extraction, all models use
`HasOwner`, public endpoints are throttled, 20 listeners are one
explicit map, mutation guards delegate to `OwnerWriteGuard`, and
`SignalCondition` is canonical — with zero rated findings remaining.

## What was done

- **Recorder split (A1):** per-source `Recorders/*` behind narrow
  shapes; trusted missing fields throw, browser parsing lenient and
  documented; recorder slimmed 887→207 lines — see
  `code-fixes-record.md`.
- **Single owner path (A2):** parallel trait deleted; all 12 models
  `HasOwner` — see `code-fixes-record.md`.
- **Endpoint hardening (A3):** named throttle + payload caps,
  allowlists, per-property/IP limits on all four collect routes —
  see `code-fixes-record.md`.
- **Listener map (A4):** 20 listeners → 1 explicit
  `SignalEventMap` + `RecordCommerceSignal` — see
  `code-fixes-record.md`.
- **Guards (A5):** already delegated to `OwnerWriteGuard` in
  baseline — verified, no change needed — see `code-fixes-record.md`.
- **Canonical condition (Q1):** `SignalCondition` owns matching + SQL
  compilation with fail-closed — see `code-fixes-record.md`.
- Suites: Signals 98 passed (748 assertions), FilamentSignals 26
  passed (80 assertions); PHPStan level 6 clean (83 + 72 files).
  No migration required.

## Residual notes

- Long-range rollup reads, production-scale `EXPLAIN` review,
  `InteractionRuleService` extraction, and the clarity-only job
  rename remain deferred — none blocks correctness at current scale.
- No relation to the identity sweep (verified zero references) —
  earlier integration caution was overstated and is withdrawn.

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
