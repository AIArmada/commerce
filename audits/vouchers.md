# Vouchers Audit — DONE (2026-09-11)

## Verdict

`vouchers` and `filament-vouchers` have passed the implementation review. The
validator, money, lifecycle, usage, owner, stacking, and statistics findings
are closed or explicitly deferred below. The persisted `status` string values
and the frozen cart application boundary remain unchanged. No migration is
required.

## What was done

- **Validator and counts:** the weaker `VoucherService::isValid()` and
  `canBeUsedBy()` paths have no in-repo callers and were dropped from the
  service surface; `ValidateVoucherCode` remains the canonical thin action
  where callers require it. `QueriesVouchers::voucherQuery()` now preloads
  `usages_count`, as shown at `packages/vouchers/src/Concerns/QueriesVouchers.php:19-33`.
- **Lifecycle:** `scopeLive()` combines status, start/expiry windows, and
  usage availability at `packages/vouchers/src/Models/Voucher.php:247-273`;
  depletion, activation, and pause writes go through state transitions. Status
  reads are single-sourced through the state object, while wall-clock expiry is
  evaluated by `isExpired()`/`live()` and reconciled by the scheduled command.
- **Cascade integrity:** voucher relation cleanup is wrapped by the model
  delete transaction at `packages/vouchers/src/Models/Voucher.php:621-629`.
- **Stacking:** the unused conflicting policy factories were removed;
  `StackingPolicy::fromConfig()` is the sole construction path and its
  effective default is documented at `packages/vouchers/docs/03-configuration.md:59-61`.
- **Money:** the float calculation path was removed from the domain calculator
  and compound conditions. Money and basis points stay integer minor units;
  the remaining cart `apply(float)` boundary is the frozen external contract,
  not an internal money conversion.
- **DTO boundary:** `VoucherData` rejects float money/basis-point inputs at
  construction and keeps the runtime rejection as defense in depth; it does
  not coerce floats. The guard is at `packages/vouchers/src/Data/VoucherData.php:340-350`.
- **Usage counters:** `times_used` is derived from `voucher_usage` redemption
  rows and is the redemption truth. `applied_count` remains the separate cart
  application-funnel counter; their definitions and writers are documented at
  `packages/vouchers/docs/09-usage-tracking.md:9-22`, so the counters are
  deliberately non-competitive rather than split increment paths for one
  quantity.
- **Ownership and money surfaces:** owner-context requirements are documented,
  cross-owner writes remain guarded, and Filament forms use the integer money
  helper. The three pre-existing voucher cart failures were reproduced and
  fixed in the owned voucher integration seam; current cart core was not
  modified.
- **Filament adapter:** the stats aggregator delegates per-voucher definitions
  to the domain model at `packages/filament-vouchers/src/Support/VoucherStatsAggregator.php:91-101`,
  and the navigation fallback now matches shipped config. The two adapter-only
  PHPStan diagnostics recorded during review were cleared with type annotations
  at `packages/filament-vouchers/src/Support/MoneyHelper.php:120-123` and
  `packages/filament-vouchers/src/Widgets/VoucherSuggestionsWidget.php:78-81`;
  owner shape and lifecycle scheduling are documented.

## Audit deviations

- **F1 relocation CLOSED.** The three files were moved to
  `filament-vouchers`, consumers rewired, zero old-namespace references —
  see the F1 closure entry in `code-fixes-record.md`.
- DTO float fields are rejected, not coerced, because construction-time
  rejection is the chosen contract and the runtime guard remains useful for
  untyped payloads.
- Both usage counters remain because conversion reporting needs application
  funnel and redemption truth; their semantics are now explicit and
  non-competitive.
- The package-local `tests/` directories remain absent; the repo-root Area
  suites and targeted regression tests provide the verified coverage.

## Residual notes

- The F1 move is closed (three files in `filament-vouchers`, consumers
  rewired, zero old-namespace references).
- `HasVoucherOwnership` and `Support/CartWithVouchers` were retained because
  live callers were found; the audit's zero-caller deletion proposal was
  falsified.
- `ValidateVoucherCode` was retained as the single thin action because it has
  live callers; only the weaker parallel service methods were removed.
- Affiliate commission `share` values remain decimal ratios; the integer-only
  rule applies to money and basis points, not that separate affiliate
  contract.

## Verification

- PHPStan level 6 is clean on all six PPV source trees; the domain tree
  command was `php -d memory_limit=1G ./vendor/bin/phpstan analyse packages/vouchers/src --level=6`.
- Vouchers: **892 passed, 7 skipped, 1717 assertions** (`tests/src/Vouchers`).
- FilamentVouchers: **42 passed, 275 assertions** (`tests/src/FilamentVouchers`).

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
