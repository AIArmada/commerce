# Cart Audit — DONE (2026-09-09)

## Verdict

The cart package (`cart` + `filament-cart`) has passed full review
and implementation. Snapshots are a core-owned read projection,
storage has one contract, the global helper is gone, prices speak
minor-unit ints with an optional money accessor, owners are
non-fillable, currency is canonical, Octane flushes, and real
coverage (1229 tests) pins it — with zero rated findings remaining.

## What was done

- **Snapshot collapse:** `Cart\Snapshots\CartSnapshot` (+ items,
  conditions) owned by core; sync manager + event wiring in core
  provider; filament adapter-only; snapshot migrations moved
  core-side — see `code-fixes-record.md`.
- **Conditions + abandonment:** stored-condition core actions with
  thin filament shells; single hardened clear-abandoned command —
  see `code-fixes-record.md`.
- **Contracts:** `CartStorageInterface` deleted (sole
  `StorageInterface`); `Target`/`ConditionPresets` builders replace
  `TargetPresets`; global `cart()` helper deleted — see
  `code-fixes-record.md`.
- **Money + identity:** minor-int `getBuyablePrice()` with distinct
  `getBuyableMoney()`; owner keys out of `$fillable` (context
  assignment only); canonical currency presenter everywhere — see
  `code-fixes-record.md`.
- **Hygiene:** Octane lifecycle flushing; centralized limits; operator
  branding env-driven; provider config discipline — see
  `code-fixes-record.md`.
- **Caller migration (integration):** snapshot-model, manager, and
  preset references rewired in vouchers (7 files), affiliates bridge,
  signals event strings, demo app/tests, root composer mappings,
  and docs examples — see `code-fixes-record.md`.
- **Falsified, correctly:** `InMemoryStorage` (live consumers),
  `ExampleRulesFactory` (active fixture), "zero tests" (centralized
  suites) — see `code-fixes-record.md`.
- Suites: Cart 1052 passed + 2 skipped (2731 assertions),
  FilamentCart 177 passed (604 assertions); PHPStan level 6 clean
  (123 + 33 files). Canaries green: Orders 323, Checkout 266,
  Cashier 256, Signals 98, Vouchers 889 (3 pre-existing failures
  below), FilamentVouchers 41.

## Residual notes

- **Pre-existing failures (not this track):** 3 voucher
  remove/clear/replace tests fail identically with caller updates
  stashed — cart/voucher condition-storage seam predates this work.
  Logged for the vouchers/cart track with the stash proof.
- Filament test for the owner-scoping bridge required the canonical
  `cart.owner.enabled` key (not the vestigial `filament-cart` key)
  plus context assignment — updated; vestigial key left untouched.
- `item_id` retained intentionally (original cart key, distinct from
  snapshot UUID relation).

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
