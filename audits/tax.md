# Tax Audit — DONE (2026-09-11)

## Verdict

The `tax` + `filament-tax` pair is cleared. Every rated finding is
implemented, falsified with source evidence, or recorded as an explicit
deferral. Calculator ownership, unknown-value handling, deletion integrity,
rounding semantics, and Filament validation are aligned. No migration is
required.

## What was done

- **A-1 / A-2 — owner isolation and contract shape:** `TaxCalculator`
  resolves the owner once and carries it through rate, exemption, and zone
  queries (`packages/tax/src/Services/TaxCalculator.php:31-67,105-121`).
  `TaxOwnerScope` applies the shared owner-query semantics and explicit global
  behavior (`packages/tax/src/Services/TaxOwnerScope.php:21-62`); the model
  overrides that advertised a divergent `scopeForOwner` default were removed.
  Cross-tenant calculator reads and writes are covered by the isolation tests.
- **A-3 / A-4 — explicit unknown handling:** zero-tax results use nullable
  persisted identifiers rather than fabricated `TaxRate` or `TaxZone` models
  (`packages/tax/src/Services/TaxCalculator.php:162-204`,
  `packages/tax/src/Data/TaxResultData.php:16-28`). Exemption lookup accepts
  the actual model morph class or requires an explicit type and throws for an
  unknown type (`packages/tax/src/Services/TaxCalculator.php:127-159`);
  hardcoded customer-type guesses were removed.
- **A-5 / D-2 — application integrity:** zone deletion is owner-aware and
  removes or guards its rates at the model boundary
  (`packages/tax/src/Models/TaxZone.php:253-298`). Tax-zone codes and tax-class
  slugs are checked within their owner tuple in model hooks and Filament
  forms (`packages/tax/src/Models/TaxZone.php:301-325`,
  `packages/filament-tax/src/Resources/TaxZoneResource/Schemas/TaxZoneForm.php:34-50`).
- **C-1 / C-2 — honest naming and pinned math:** the setting is now
  `round_per_rate`, and the compound-tax base formula is documented beside
  the implementation (`packages/tax/src/Services/RateApplier/StandardRateApplier.php:12-17,45-70`).
  Inclusive/exclusive and compound-rate cases are covered by the Tax unit
  tests.
- **L-1 / L-3 / D-1 / D-3 / M-1 / M-2 / M-3 / S-1 / S-2 / S-3 — compliant
  surfaces:** PHP 8.4, UUID/owner models, configured table prefixes,
  basis-point rates, immutable lifecycle timestamps, resolver seams, private
  owner-checked certificate downloads, and owner-context batch commands were
  verified. Filament forms use owner-aware uniqueness, and no tax webhook or
  amount-tampering surface was found.
- **T-1 / T-2 — stale coverage claims:** the root Tax and FilamentTax suites
  and the added model factories now cover the calculator, isolation,
  lifecycle, and resource paths; the original “zero tests” and “no factories”
  findings were falsified by the current repository state.

## Audit deviations

- **L-2 remains an explicit deferral:** “`tax` migrations each define `down()`
  with `dropIfExists` while `shipping`/`checkout` use
  `commerce_schema_create_if_missing` without `down()` — pick one convention
  (keep `down()`; harmless).” The existing `down()` convention is retained.
- **P-1 remains an explicit deferral:** “`getRates()` orders by
  `is_compound ASC, priority DESC` on an index of `(zone_id, tax_class,
  is_active)` — the sort is not covered; fine at this cardinality, revisit
  only with thousands of rates per zone.”
- **P-2 remains an explicit deferral:** “Calculator dispatches
  `TaxCalculated`/`TaxZoneResolved`/`TaxExemptionApplied` per line — ensure
  checkout calls it per cart total, not per unit, or listeners must be cheap.”
- The `checkout` and other dependent packages were read-only for this
  closure; their canary behavior is recorded by the review record and was not
  rewritten here.

## Residual notes

The retained migration-convention, scale-dependent index, and event-cardinality
decisions are deliberate follow-ups, not unresolved isolation or correctness
findings. No compatibility shim was added.

## Verification

- Tax Area: **194 passed, 442 assertions**.
- FilamentTax Area: **27 passed, 71 assertions**.
- All Pest commands used `--parallel`; no full-suite run was performed.

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
