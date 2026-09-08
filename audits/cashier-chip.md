# Cashier CHIP Audit — DONE (2026-09-08)

## Verdict

The native CHIP billing package (`cashier-chip` +
`filament-cashier-chip`) has passed full review and implementation.
Duplicated status mapping and billing formatters are deleted in favor
of canonical contracts, eager loading is explicit, renewals run
owner-batched without blanket scope strips, voucher integration fails
loud, dates are immutable, ids are ordered UUIDs, and payment/invoice
history is backed by real data — with zero rated findings remaining.

## What was done

- **Canonical-owner collapse** (local status mapping + billing
  formatter deleted; `PurchaseData` + `MoneyFormatter` adopted;
  `findBillable()` retained for the read-only `cashier` caller) — see
  `code-fixes-record.md`.
- **Explicit eager loading** (`$with` removed; `loadMissing` at call
  sites; owner-batched renewal queries) — see `code-fixes-record.md`.
- **Loud voucher bridge** (`VoucherIntegration::assertAvailable` +
  config/docs) — see `code-fixes-record.md`.
- **Renewal scope narrowing** (`OwnerBatchRunner`, no blanket strip) —
  see `code-fixes-record.md`.
- **Hygiene** (`CarbonImmutable`, ordered UUIDs, create-then-prune,
  backed payment/invoice methods) — see `code-fixes-record.md`.
- Suites: CashierChip 545 passed (908 assertions),
  FilamentCashierChip 93 passed (226 assertions); PHPStan level 6
  clean on both source packages. No migration required.

## Residual notes

- `CashierChip::findBillable()` stays because read-only `cashier`
  calls it; if cashier ever drops the dependency, re-evaluate.
- Callers of the deleted/implemented stubs in `cashier` and
  `filament-cashier` were verified (seam tests green) but live in
  read-only packages — any future signature change must coordinate.

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
