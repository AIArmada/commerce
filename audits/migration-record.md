# Migration Track Record

Completed 2026-09-07. This file is the historical record of every migration-track item from the package audits: what was implemented, what was dropped or corrected after independent verification, the evidence, and the remaining deployment gates.

Per-package audit files (`audits/*.md`) no longer contain settled migration content — they describe remaining work only. Each cleaned file points back here.

- Worktree at completion: `7645f4a38`, clean. 24 commits after base `32f10440b`.
- All migration files are new additions; no shipped migration was edited.
- New migrations contain zero `constrained()` / `cascadeOnDelete()`.
- Review method: every implemented claim was re-verified against source (files, `rg` repo-wide, live test spot-checks) before being recorded as done.

## Pricing — implemented

- **Migration** `2026_09_07_073442_add_tierable_lookup_index_to_price_tiers_table.php`: composite index `(tierable_type, tierable_id, price_list_id)` on `price_tiers`. Guarded, idempotent, index-only. No enforcement code needed — `TierResolver` already filters correctly.
- Evidence: `packages/pricing/database/migrations/2026_09_07_073442_*`, commit `a654bde4e` (+ coverage hardening `fcc9dfa0c`).
- Verified: prior migration had only `(min_quantity, max_quantity)`; resolver filters confirmed; `PriceCalculator` confirmed as the production consumer via repo-wide `rg`.

## Organizations — implemented

- **Migration** `2026_09_07_074034_add_organization_integrity_indexes.php`: unique slug, unique `(organization_id, user_id)`, index `(organization_id, role)`. Drops the redundant plain indexes first, resolves real index names, all guarded.
- **Enforcement**: `CreateOrganizationAction` retries slug creation on SQLSTATE uniqueness conflicts (3 attempts, inside `DB::transaction`).
- Evidence: commit `36e26266a` (+ hardening `b611dc155`).
- Verified: migration content and retry logic read in source.
- **Gate**: run duplicate preflights on the live DB before migrating (uniques fail on dirty data) — see [Deployment gates](#deployment-gates).

## Signals — dropped (already existed)

- Audit claim (missing unique on `(tracked_property_id, idempotency_key)`) was stale: the unique is at `2001_01_01_000004_create_signals_events_table.php:42`. No migration written. Regression test strengthened instead (commit `41870ef74`).

## Shipping — corrected (no migration, by design)

- Asymmetry confirmed (`ShippingRate` has no `HasOwner`, `ShippingZone` is scoped), but all rate reads traverse the zone (`$zone->rates()`, `whereHas('zone')` in Filament, `CalculateShippingRate` action for checkout). Repo-wide `rg` found no direct production `ShippingRate::query()` consumer.
- Decision: a second owner tuple on child rates would duplicate ownership state and its sync burden. Retired the audit recommendation; zone guards retained (commit `0d29e2da6` hardened owner resolution).

## Persons — dropped (explicit design)

- `persons/CONTEXT.md` states "Shared identity by design; no owner scope." Repo-wide `rg`: zero `HasOwner` hits in package, zero external consumers of `HasTitles`/`HasAffiliations`/`HasCredentials` (used by `Person` internally only).
- Title uniqueness claim also wrong: the `(category_id, usage_position, sort_order)` unique supports the transactional reorder logic in `ReorderTitleAction`. Retained.
- Residual (not a migration): the traits remain attachable to future scoped models. No guardrail added — a prohibitive test was rejected as blocking legitimate future design. If this worries you later, pin it then.

## Growth — corrected (no migration) + parity enforcement added

- Audited `subject_type`/`subject_id` columns do not exist; assignments use `subject_key` with an existing `unique(experiment_id, subject_key)`. No `rg` hits for the claimed columns anywhere.
- Slug-collision concern evaporated on re-derivation: `owner_scope` is deterministically derived from the owner tuple (`HasOwnerScopeKey`, asserted in `ExperimentTrackedPropertyValidationTest`), so two owners cannot share a scope value.
- **Added**: `GrowthServiceProvider::assertOwnerModeMatchesSignals()` — boot-time throw unless Growth and Signals owner modes match (commit `e6de8d5d6`).

## Addressing — implemented

- **Migration** `2026_09_07_090000_add_owner_columns_to_addressing_tables.php`: `nullableUuidMorphs('owner')` on `addresses`, `addressables`, `address_snapshots` only. Reference tables untouched.
- **Enforcement**: `HasOwner` + scope config on all three instance models, `AddressOwnerGuard` on write/attach/snapshot paths, `OwnerUiScope` on Filament resources, events-side `Addressable` trait hardened with owner-guarded relations (commits `048a8d686`, `963e00bc3`, `ce5550f98`, `0c8521a41`).
- `include_global` set to `false` (audit suggested `true`; `false` chosen — tenant PII must not leak through global reads; global access uses explicit global context).
- Dropped subclaim: the snapshot composite index already comes from `uuidMorphs('snapshotable')`.
- **Gate**: legacy ownerless rows were NOT backfilled — see [Deployment gates](#deployment-gates). This is the top deployment risk in the whole track.

## Vouchers — implemented

- **Migrations**: `2026_09_07_100000_backfill_voucher_promotion_ids.php` (valid-UUID-only, never overwrites, chunked, re-runnable), `2026_09_07_100001_drop_voucher_credit_tables.php`, `2026_09_07_100002_drop_redundant_voucher_code_index.php`.
- **Code**: provenance canonicalized on `promotion_id` (metadata fallback removed), dead `VoucherAssignment`/`VoucherTransaction` models, relations, readers, and metadata writers deleted after repo-wide reader checks (zero remaining). `HasVouchers` wallet functionality correctly retained (audit's delete-the-trait advice was wrong).
- **Cross-package fixes**: `filament-cart` `ViewCart` now imports the real `Vouchers\Filament\Extensions\CartVoucherActions`; affiliates `VoucherBridge` checks the real `Vouchers\Models\Voucher`; checkout revalidation registered against the existing `CheckoutStarted` event (guarded); stale doc imports fixed including `filament-vouchers` examples (commits `a0d804fd0`, `4c6b2c732`, `af24a2edb`, `2001bd53d`, `83a3c331e`, `28ce28c2b`, `6c452d751`).
- **Gate**: back up the dropped tables before migrating — irreversible.

## Promotions — implemented (reversed round-1 conclusion)

- Round 1 found BOGO live through the voucher path and dropped the deactivation. Round 2 re-derived it: BOGO-as-`PromotionType` discounted 0 on the direct path, so the type was removed coherently — `PromotionType` now has only Percentage/Fixed, `calculateDiscount()` is exhaustive, zero `PromotionType::BuyXGetY` references remain in any package, config, or test.
- **Migration** `2026_09_07_110000_deactivate_buy_x_get_y_promotions.php`: canonicalizes legacy rows to inactive fixed-zero (sets `deactivated_at` where the column exists) before the enum shrink, preventing hydration failures. No `down()` (permitted; irreversible by nature).
- **Deletions**: dead strategy subsystem, dead action/command/listener files, demo seeder rows, Filament alignment (commits `7732638a7`, `ad36b8059`, `3acbfca73`, `ea7471b1c`, `d3638e5fa`).
- BOGO-as-`VoucherType` remains live and untouched — voucher mechanics were never the problem.
- **Gate**: run the migration before workers boot new code (workers hydrating the removed enum case crash).

## Docs — dropped (target doesn't exist)

- Audited `doc_workflows.payload` is absent; actual schema is `docs_workflows.rules`, already configurable via `commerce_json_column_type()`. No migration. Verified in migration source and model.

## Not in this track

- **Inventory** (`reservation_group_id` migration, decimal-column drops) was never implemented — `audits/inventory.md` is unchanged and still describes open work.

## Deployment gates

In order:

1. **Addressing legacy rows** — decide before enabling in production: backfill owners onto existing ownerless rows, or confirm every legacy consumer reads through explicit global context. Otherwise legacy addresses silently vanish from scoped reads.
2. **Organizations duplicates** — run the slug and membership duplicate preflights on the live DB; dedupe before migrating.
3. **Promotions timing** — migrate before workers boot the new code.
4. **Voucher backup** — back up `voucher_assignments` / `voucher_transactions` before migrating.
5. **PHP 8.4 CI** — local verification ran on PHP 8.5.8; gate deployment on the 8.4 pipeline.
6. **Docs demo failure** — one reported demo-fixture failure ("Document templates require at least one layout block") could not be located in the test tree; the producing file is untouched by all 24 commits, consistent with pre-existing and unrelated. Reconfirm if it resurfaces.

## Commit list (after base `32f10440b`)

`a654bde4e`, `36e26266a`, `41870ef74`, `048a8d686`, `963e00bc3`, `ce5550f98`, `a0d804fd0`, `7732638a7`, `4c6b2c732`, `d3638e5fa`, `fcc9dfa0c`, `b611dc155`, `0c8521a41`, `0d29e2da6`, `e6de8d5d6`, `af24a2edb`, `ad36b8059`, `ea7471b1c`, `3acbfca73`, `2001bd53d`, `83a3c331e`, `28ce28c2b`, `6c452d751`, `7645f4a38`.
