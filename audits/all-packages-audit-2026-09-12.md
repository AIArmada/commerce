---
title: All Packages Audit — Bugs, Security, Performance
date: 2026-09-12
scope: packages/* (67 packages)
mode: verified merged — false positives removed, severities corrected
source: verified-bugs-2026-09-12.md + verified-security-2026-09-12.md + verified-performance-2026-09-12.md merged back
---

# All Packages Audit — Bugs / Security / Performance (2026-09-12, verified)

Scope: all 67 packages under `packages/*` (36 core/domain + 31 `filament-*` adapters).
Method: `CONTEXT.md` + `src` listing per package, `rg` for known anti-patterns, targeted `read` of models/actions/services/webhooks/migrations/filament resources. Each item re-read at cited `file:line`. No code changed.

This file merges the three verified splits back into one. FALSE items are removed from the body and listed once in §7 (do not re-file). Corrected severity shown where the original was inflated.

Baseline automated scans (2026-09-12):

- DB constraints/cascades: `rg "constrained\(|cascadeOnDelete\(" packages/*/database` → **0 hits. PASS** (app-level cascades per contract).
- `SoftDeletes`: **0 hits. PASS**.
- `static $navigationGroup`: **0 hits. PASS**. Flat `navigation_group`: **0 hits. PASS**. All filament resources use `getNavigationGroup()` reading nested `navigation.group` config.
- `HasUuids` + `getTable()` via config: present on ~all persisted models. Exceptions: `commerce-support/Tag` (no `getTable()`), `chip/ChipIntegerModel` intentional int PK, `jnt/JntWebhookLog` hardcoded table.
- `owner_type/owner_id` in `$fillable`: `customers/pricing/contacting/commerce-support/growth` correctly exclude; `products/tax/events/inventory-reservations/vouchers` include — but `products/tax` are mitigated by `saving` guards that throw on cross-tenant (defense-in-depth, MEDIUM, not CRITICAL).
- Raw SQL: only bound params / PDO-quoted ids / constant `1=0`. No SQLi. `LOWER(name)` lookups unindexed (perf, not injection).
- Webhooks: `chip/checkout/commerce-support` fail-closed in prod; `jnt` fail-open opt-out gap kept; `cashier` primitive correct but unenforced.

Related prior audits: `audits/README.md` index + per-package files (historical).

## Global positives (keep)

- Money as integer minor units + basis points consistently (`price/compare/commission_minor`, `rate_base_bp`).
- No DB FK constraints/cascades, `uuid('id')->primary()`, `foreignUuid` without constraints.
- `OwnerWriteGuard` / `OwnerUiScope::apply` / `forOwner` used widely; filament-customers/docs/orders/jnt are reference implementations.
- Atomic patterns exist to copy: `promotions/Promotion::tryIncrementUsage`, `vouchers/RecordVoucherUsage` (txn+lock+idempotency unique), `affiliates/ClaimScheduledPayout` (locks + FIFO + operation_key unique), `growth/ResolveExperimentAssignment` (txn+lock+retry).

## Fix-first queue (verified CRITICAL/HIGH only)

Former entries removed as FALSE (see §7, do not re-file): checkout `PaymentCallbackController:188` never-match, `filament-persons` unscoped list, shipping `ShippingZoneResolver` Octane memo. Downgraded out of this queue (see body): products/tax `owner_*` fillable (MEDIUM, guards throw), seating `SeatMap` Livewire (MEDIUM enumeration), events `ScopesByEventOwner` (MEDIUM-verify matrix), inventory reports blanket (MEDIUM-verify).

| # | Package | Location | Issue | Sev |
|---|---------|----------|-------|-----|
| 1 | vouchers | `Actions/UpdateVoucher.php:25-27` | Unscoped `where(code)` write — any tenant can rewrite another's voucher | CRITICAL |
| 2 | affiliates | `Actions/Payouts/CreatePayout.php:36-100` | Double-spend race: read-no-lock → create payout → conditional claim | CRITICAL |
| 5 | jnt | `Webhooks/JntSpatieSignatureValidator.php:17-22` | `verify_signature=false` accepted with no prod fail-closed (checkout refuses) | CRITICAL |
| 23 | events | `Models/Event.php` (no booted cascade) | Delete orphans entire subtree | CRITICAL |
| 6 | cashier | `Gateways/StripeGateway.php:413-423` | `handleWebhook` re-encodes body → Stripe HMAC never verifies | HIGH |
| 7 | cashier | `Actions/SyncWebhook.php:21-35` | No enforced `verifyWebhookSignature()` (same for `WebhookReplayCommand`) | HIGH |
| 8 | checkout | `Services/CheckoutService.php:78-91` | `resumeCheckout` uses unscoped `find()` — session enumeration | HIGH |
| 9 | checkout | `Models/CheckoutSession.php:80-116` | `owner_*/status/totals/order_id` fillable → free-order / hijack | HIGH |
| 11 | orders | `Models/Order.php:399-409,380-384` | `recalculateTotals` tax-inconsistent; `getBalanceDue` adds refunds back | HIGH |
| 12 | orders | mass assignment | `owner_*/status/*_at` fillable on Order/Payment/Refund/Item | HIGH |
| 13 | affiliates | `Actions/Conversions/MatureConversion.php:26-48` | Lock-free, double-release (approved_at part removed — auto-set) | HIGH |
| 14 | affiliates | `ApplyConversionAccounting::reject:90-93` | Can drive `holding_minor` negative | HIGH |
| 15 | affiliates | `ClaimScheduledPayout` vs `UpdatePayoutStatus:40-58` | Cancelled/failed payouts never refund balance | HIGH |
| 16 | communications | `Actions/CreateTrackingTokenAction.php:26-47` | Plaintext token discarded, `getToken()` returns hash; no bearer route | HIGH |
| 17 | communications | `AddCommunicationRecipientAction`, `RecordTrackingInteractionAction` | `findOrFail` without `OwnerWriteGuard` | HIGH |
| 18 | ticketing | `Actions/TransferPassToHolderAction`, `IssuePassesAction` | Arbitrary `holder_type/id` morph, no owner check | HIGH |
| 19 | ticketing | `Services/DefaultPassTransferService.php:24-47` | No lock, no same-pass/owner check → double-spend | HIGH |
| 20 | seating | `Actions/ConvertHoldsToAllocationsAction` | No owner validation, no `lockForUpdate` → double allocation | HIGH |
| — | seating | `SeatHold/SeatAllocation` (no `booted()`) | Any `seat_id/held_by_*/allocated_to_*` accepted outside allocator | HIGH |
| 25 | events | `Services/RegistrationService.php:51-123,291-312` | Forged `total_amount/currency`, unvalidated `ticket_type_id`, no Validator | HIGH |
| — | events | `EventRegistrationItem:46-54` | `ticket_type_id` never validated same-event/owner | HIGH |
| 26 | inventory | `Models/InventoryLevel.php:81-99` | `quantity_on_hand/reserved/available` fillable — bypasses ledger | HIGH |
| — | inventory | `InventoryReservation:50-59`, `InventoryOperation:46-53` | `owner_*/order_id/status` fillable, no `booted` guard | HIGH |
| 28 | promotions | `Support/PromotionPerformanceInsights.php:114-123,203-206` | Owner-blind analytics + unscoped order load | HIGH |
| — | promotions | `DeactivateExpiredPromotionsCommand:23-26` | Cross-tenant sweep; one tenant's cron mutates others' | HIGH |
| 29 | vouchers | `Models/VoucherWallet.php:81-101` | `claim()` check-then-set race → double redeem | HIGH |
| 30 | engagement | `Services/DefaultEngagementManager` follow/bookmark/react/respond | No lock + no unique → duplicates skew counters | HIGH |
| — | engagement | Reminders `markSent/Failed:111-127` | No status precondition, no lease → double-send | HIGH |
| 31 | feedback | `Actions/SubmitFeedbackResponseAction.php:139-149` | One-response check without lock/unique → duplicates | HIGH |
| 32 | chip | `Data/PurchaseDetailsData.php:96`, `ProductCollection.php:57,69`, `ProductData:105` | Float math on minor units, truncation | HIGH |
| 34 | cart | `Storage/DatabaseStorage.php:683-724` | CAS insert race, no duplicate-key retry | HIGH |
| 35 | cart | `Actions/MigrateGuestCartToUserAction.php:107-125,184-214` | Non-atomic migration → duplicated carts | HIGH |
| — | checkout | `CheckoutSession:183,204,236` | Lost-update on JSON blobs; nested txn lengthens locks | HIGH |
| — | shipping | `CreateShipment:77` | Event before commit → ghost waybill on rollback | HIGH |
| 37 | shipping | `Integrations/OrderFulfillmentHandler.php:224,288,345` | Unscoped tracking lookup + unvalidated `location_id` | HIGH |
| — | jnt | `ProcessJntWebhook:493-500` | `CarbonImmutable::parse()` throws on bad `scanTime` → retry loop | HIGH |
| — | jnt | `JntTrackingEvent/Parcel/Item creating` | Inherit-without-authorize, no context check | HIGH |
| — | authz | `Services/ImpersonateManager.php:98-132` | `take()` performs no authorization | HIGH |
| — | membership | `Models/MembershipApplication.php:38-50` | `status/granted_role/reviewer_*` fillable bypasses Approve/Reject | HIGH |
| — | ticketing | `Pass:85-94`, `TicketType:79-86` | No deleting cascades → orphans | HIGH |
| — | cashier | `Webhook::constructEvent` unenforced | Primitive correct, callers never verify → forged-event processing | HIGH |
| — | cart | `CheckoutService:51-71` gap | No owner assertion on `startCheckout` (MEDIUM, kept near queue) | MEDIUM |

Perf HIGHs (fix with the above, not in security queue): customers `Segment` OOM, inventory reports `get()`, promotions `withinCustomerLimit` + insights loads, checkout 20–30 UPDATEs, orders N×5 `sum()`, seating `getStatusProperty` OOM, cashier remote N+1 invoices.

---

## 1. Foundation

### commerce-support
Bugs:
- `src/Support/MoneyNormalizer.php:32` LOW (was MEDIUM) — `toDollars(int): float` display helper only; do not use for persist/calc.
- `src/Support/MoneyNormalizer.php:45` LOW — `format()` returns `formatCurrency()` typed `string`, no `false` check → TypeError on failure.
- `src/Actions/ProcessWebhookCallAction.php:65-69` LOW — failure `update(exception=(string)$e)` outside txn, unbounded (DB bloat / secret leak).
- `src/Models/Tag.php:10` LOW — no `getTable()`, breaks prefix contract.
Security:
- `src/Webhooks/CommerceSignatureValidator.php:71-93` LOW (was MEDIUM) — `hash_equals` + `hmac` correct; no timestamp/nonce/replay, no `sha256=` strip. Base-class standard.
- `src/Models/Report.php:47-56` MEDIUM — `status/reviewed_by_*/timestamps/internal_notes` fillable → forge reviewer + terminal timestamps.
Performance:
- `src/Support/OwnerBatchRunner.php:92-95` MEDIUM — `distinct()->get()` loads all owner tuples; chunk it.
- `src/Support/OwnerBatchRunner.php:113,138` LOW (was MEDIUM) — global `config(include_global)` flip per-run with `finally` restore; correct but Octane-fragile.
- `SeedLanguagesAction:34-52` LOW — per-row `where(code)->first()` + insert/update (seed-only, small N).

### authz
Bugs: `Models/Role.php:65-81` LOW — caller-supplied teams key wins (`! array_key_exists`); harden if ever request-adjacent. (`Permission.php` part removed — no teams code there.)
Security:
- `Services/ImpersonateManager.php:98-132` HIGH — `take()` has zero authorization; relies entirely on callers.
- `Models/Role.php:110-112` LOW — `findByParam` loops raw `$params` into `where($key,$value)`; allowlist if ever exposed (currently `protected static`).
Performance: clean. `AuthzScope deleting` 5× `DB::table` in one txn; volume bounded.

### membership
Bugs:
- `Models/MembershipApplication.php:38-50` HIGH — `status/granted_role/reviewer_*/reviewed_at/cancelled_at` fillable, no model default; direct `create()` bypasses Approve/Reject.
- `Actions/InviteMemberAction.php:34-45` MEDIUM — existing Pending returned as-is, `$token` stays null → no resend.
- Missing composite unique `(subject,email,role,status)` MEDIUM — only `token` unique; `lockForUpdate` dedupe races.
Security: clean — `OwnerWriteGuard` + `lockForUpdate` on Accept/Revoke/Cancel/Approve/Reject; `hash_equals` token; `token` hidden.
Performance: `AddMemberAction.php:65` LOW — `hasColumn` schema check inside txn per call (cache it).

### organizations (org IS owner, no HasOwner — correct)
Bugs:
- `Models/Organization.php:148-171` MEDIUM — `transitionToStatus/Visibility` never clears stale counterpart (`suspended_at/archived_at/published_at`).
- `Models/Organization.php:48-60` MEDIUM — `status/visibility/*_at/created_by` fillable bypasses transitions (only `created_by` guarded in `saving`).
Security: clean — `TransferOrganizationOwnershipAction` txn + lock + single-owner invariant.
Performance: clean, indexes present.

### persons (global identity — no owner scope correct)
Bugs:
- `Models/Person.php:82-87` MEDIUM (was HIGH) — `names/titleAssignments/credentials/affiliations ->get()->each->delete()`, no txn/chunk; partial-delete orphans.
- `Models/Person.php:126-174` MEDIUM — up to 100× `exists()` slug probes + extra `names()` query per save; no 23000 retry → 500 on race.
- `Models/Person.php:255-261` LOW — `transitionStatus(): void` mutate-no-save (style only).
- `Models/Person.php:59-71` LOW (was MEDIUM) — `slug/searchable_name/status/published_at` fillable, but `saving` overwrites slug/searchable + `syncStatusLifecycle` repairs `published_at`.
Security: clean — `TitleAssignment saving` existence checks; `ReorderTitleAction` PDO-quoted `CASE` + lock + txn.
Performance:
- `getFormattedNameAttribute:202-227` MEDIUM N+1 — lazy `titleAssignments()->where(Active)->with(title.category)->get()`; eager-load or `scopeWithFormattedName`.
- `TitleAssignment saving` 1–2 `exists()` per row LOW — bulk import should `whereIn` prefetch.
- `Person:82-87` cascade is also perf: N+1 deletes, no chunk.

### addressing
Bugs:
- `Models/Address.php:74-78` MEDIUM — `deleting` bulk deletes pivots (skips events) + nulls snapshots, no txn.
- `ImportAddressAreasAction.php:36-214` MEDIUM (was HIGH) — 3–6 queries/row, no txn/chunk; offline import perf + partial on abort.
- `ImportPostalCodesAction.php:25-132` MEDIUM (was HIGH) — per-row `DB::transaction` exists (`:63`), but still per-row queries, no chunk.
Security: clean — `AddressOwnerGuard` + `Addressable:saving` + morph checks; search bound params + clamped limit; seed `DB::table` global-only.
Performance: `LOWER(name)` full scans MEDIUM — `NormalizeAddressDataAction:83,108,137`, `SearchAddressAreas:84-96`, `HierarchyResolver:40,69`; add functional/lower index; cache `Schema::hasTable` (`Normalize:191-196` hits info-schema per save). `Addressable` owner + `(type/id)` + `is_primary` indexes present.

### contacting
Bugs:
- `Models/ContactMethod.php:56-74`, `SocialProfile.php:59-78` MEDIUM — `is_verified/verified_at/normalized_*` fillable, no verification guard in `saving`; `CreateContactMethodAction:42` sets `is_verified` with no visible authz.
- Null-parent `is_primary` orphans + global/owned primary split LOW — by-design, `syncSiblingPrimaryFlags` early-returns on null parent.
Security: clean — `saving` → `OwnerWriteGuard` on all 3 models; no `owner_*` in fillable.
Performance: clean — primary swap `lockForUpdate` + txn + partial unique.

### references
Bugs:
- `Models/Reference.php:95-122,240-257` MEDIUM — level-at-a-time `pluck` + per-media `delete`, bulk `delete()` skips child events (in txn, correct).
- No `transitionStatus()` LOW — `Published` without `published_at` persists silently.
Security:
- Migration `slug unique` MEDIUM (was HIGH) — `create_references_table:20 unique(slug)` + `nullableUuidMorphs('owner')` + `HasOwner`. Blocks reuse + enumeration-if-exposed; fix: `(owner_type,owner_id,slug)` composite.
- `$fillable slug/parent_id/is_canonical` MEDIUM — squatting + multiple canonicals; parent guarded, canonical unguarded.
Performance: `collectSubtreeIds` level-at-a-time LOW-MEDIUM — recursive CTE or `withCount` if deep.

### moderation
Bugs:
- `Models/Block.php:48-54` MEDIUM — `status/lifted_*/expires_at` fillable bypasses `transitionTo()`.
- `Block.php:121-142` MEDIUM — `Active` keeps stale `expires_at`; `Lifted` never sets actor.
- Scope gap MEDIUM — past-due Active in neither `scopeActive` nor `scopeExpired` until sweeper runs.
- `BlockEntityAction.php:38-54` MEDIUM — no duplicate-Active guard; double-click stacks blocks.
Security: `validateOwnerScopedModel` skips non-`OwnerScopeConfigurable` LOW (was MEDIUM) — by-design per-tenant block of shared identity.
Performance: `ExpireModerationBlocksAction:32-47` LOW (was MEDIUM) — `chunkById(100)` + per-block `expire()->save()` preserves events; keep unless proven hot.

---

## 2. Catalog / Venue

### customers (`aiarmada/customers`)
Bugs:
- `Models/Customer.php:103-104` MEDIUM (was HIGH) — `created_at/updated_at` fillable (audit-noise, not takeover).
- `Models/Segment.php:278-286` MEDIUM — `deactivated_at` never cleared on re-activate.
- `Models/Customer.php:320-328` MEDIUM — `deleting` orphans `contactMethods/socialProfiles/media`.
- `Actions/CreateCustomer.php:67-69` MEDIUM — `LinkCustomerToPerson` runs after txn returns → person-less customer on link failure.
Security: clean — `LinkCustomerToPerson` persons-global by design; customer side guarded.
Performance:
- `Segment:146-175` HIGH — `getMatchingCustomers()->get()` + `rebuildCustomerList sync(pluck)` loads all matches; chunk/paginate.
- `RebuildAllSegments:32-117` MEDIUM — per-segment `sync` + `pluck` + per-ID `event()`; N+1 events.
- Missing `(owner,status)` composite MEDIUM.

### products (`aiarmada/products`)
Bugs:
- 10 models `owner_type/id` fillable MEDIUM (was CRITICAL) — fact true, but `Product::booted` creating/updating + `Variant::creating` throw on cross-tenant; defense-in-depth.
- `Models/Variant.php` HIGH — no `updating` guard; `product_id` reassignable post-create.
- `Models/Product.php:813-820` MEDIUM — `deleting` `each(delete)` + detaches, no txn/chunk.
- `UpdateProductStatus.php:65-68` LOW — `transitionToDraft` leaves stale `published_at`.
Security:
- `Variant` no `updating` guard HIGH (above) — security impact: `product_id/owner_*` reassignment post-create.
- Filament `ProductResource:40-44` LOW — `Product::query()->forOwner()` bypasses `parent::getEloquentQuery()`; use `OwnerUiScope::apply(parent::...)`.
Performance:
- Variant cascade 1k+ deletes MEDIUM — chunk or queue.
- `Collection:453` / `Category:229` strip-then-refilter LOW (was MEDIUM) — correctly re-scoped; style risk only.

### inventory
Bugs:
- `InventoryReservation:50-59`, `InventoryOperation:46-53` HIGH — `owner_*/order_id/status` fillable, no `booted` guard (unlike Level).
- `InventoryLevel:81-99` HIGH — `quantity_on_hand/reserved/available` fillable; `saving` validates only owner/location, bypasses ledger.
- `InventoryLocation:393-469` MEDIUM (was HIGH) — levels/allocations/children cascaded; movements/batches/serials NOT cascaded.
Security: unvalidated inbound IDs MEDIUM-HIGH — `Reservation/Operation order_id`, `Level preferred_supplier_id`, `Movement user_id` (Level validates location owner only). Reports downgraded to MEDIUM-verify — `StockLevelReport/MovementAnalysisReport/InventoryService` consistently scope; need line-level proof for single `DB::query()` totals, not blanket HIGH.
Performance:
- Allocation loop O(n) writes MEDIUM — per-allocation `decrement` + `Movement::create` in txn.
- Reports `get()` no pagination HIGH — `StockLevelReport`, `MovementAnalysisReport`, `InventoryKpiService:256` load all rows → OOM. Exports are paginated (`StockLevelExport:72`, `BatchExport:60`, `MovementExport:79`, `ValuationExport:57` all `cursor()`) — split from original blanket claim.

### pricing
Bugs:
- Dual `is_active` + `deactivated_at` drift LOW — no single transition helper.
- No `amount>=0 / min_quantity>=1 / starts<=ends` validation MEDIUM.
Security: clean — owner auto-assign + cross-owner throw + `customer_id/segment_id` scoped `exists()`.
Performance: `PriceCalculator:86-205` MEDIUM — customer→segment→tier→promo→list fan-out (~100+ queries on 50-line cart, directionally); no batch preload. `clearOtherDefaults:342` bulk `update` GOOD.

### tax
Bugs:
- `owner_*` fillable MEDIUM (was CRITICAL) — fact true, but `TaxRate/Zone saving` enforce owner/global-block; defense-in-depth.
- `TaxExemption status` fillable HIGH — bypasses `approve()/transitionStatus()`.
- `TaxZone deleting` bulk skips events LOW — harmless (Rate has no `deleting` hook).
Security: `TaxCalculator:127-160` LOW (was MEDIUM) — `exemptable_type/customer_type` from context, no morph allowlist; lookup owner-scoped so arbitrary string only misses, not leaks.
Performance: `scopeForAddress:149-175` + `matchesAddress:177-205` MEDIUM — `orWhereJsonContains/Length` + PHP postcode loop per checkout; missing `(owner)` index on rates MEDIUM.

### ticketing
Bugs:
- `Pass:85-94`, `TicketType:79-86` HIGH — no `deleting` cascades; orphans holders/transfers/allocations/components.
- `TicketType status` raw string MEDIUM — no enum cast (Pass uses `PassState`).
- `DefaultPassIssuer:131-132` LOW-MEDIUM (was MEDIUM) — retry only `pass_no`; `qr/barcode` collision negligible but unhandled.
- `DefaultPassTransferService:24-47` HIGH — txn but no `lockForUpdate`, no same-pass/owner check → double-transfer.
Security:
- `TransferPassToHolderAction:38-64`, `IssuePassesAction:53-70` HIGH — arbitrary `holder_type/id` morph, no allowlist/owner check.
- `TicketingOwnerGuard` skips non-`HasOwner` LOW-MEDIUM — by-design for global models.
Performance:
- `TicketType:294-303` MEDIUM (was HIGH) — `getTotalAvailable` hydrates `inventoryLevels()->get()->sum()`; `getTotalOnHand` already SQL. Fix with `SUM(GREATEST(...))`.
- Bulk transfer nested txns + 2 writes/pass MEDIUM — chunk or queue for 100s.

### seating
Bugs:
- `ConvertHoldsToAllocationsAction` HIGH — no lock, no owner comparison, check-then-create races → double allocation. Needs `FOR UPDATE` + partial unique.
- `DefaultSeatAllocator:100-136`, `EnsureSeatHoldAction:86-122` MEDIUM-HIGH (was HIGH) — bulk `insert()` bypasses events/validation; owner manually assigned so not cross-tenant, but `seat_id` TOCTOU stands.
- `SeatHold/SeatAllocation` no `booted()` HIGH — any `seat_id/held_by_*/allocated_to_*` accepted outside allocator.
- `Seat/SeatMap/SeatSection` `each(delete)` no txn/chunk MEDIUM.
Security:
- `Livewire/SeatMap:48-93,161-177` MEDIUM (was HIGH) — no `authorize`/rate-limit; `seatable_type` public prop into `where()` with no allowlist. Enumeration, not takeover.
- Filament holds/allocations via managers may bypass `OwnerUiScope` MEDIUM — only `SeatMapResource` scoped.
Performance:
- `SeatMap getStatusProperty:112-154` HIGH — loads entire venue per render; 10k seats OOM. Page sections or cached status query.
- Allocator double query + gap locks MEDIUM — single query + `ORDER BY FIELD` + `SKIP LOCKED`.

### events (60+ models)
Bugs:
- `Event:162-172`, `EventSeries:43-49`, `EventTemplate:54-63`, `EventItinerary:40-46` HIGH — `owner_*/created_by_*/*_at/status` fillable (correction: children ALSO include owner).
- `Event` no cascade CRITICAL — no `booted/deleting`; orphans entire subtree.
- `RegistrationService:51-123,291-312` HIGH — `fill(Arr::except)` no Validator; `total_amount/currency/registrant_*/external_order_*/parent/pass_entitlements` mass-assignable; `createFromOrderItem` copies totals with `?? 0/'USD'`, no normalizer.
- `EventRegistrationItem:46-54` HIGH — `ticket_type_id` fillable, no same-event/owner validation.
Security:
- `ScopesByEventOwner:85-92` MEDIUM-verify (was HIGH blanket) — `whereNull(eventId) OR whereIn(subselect)`; leak depends on per-model matrix (models with `whereHas(event)` reject nulls).
- `EventSearchDocumentBuilder:188` LOW/MEDIUM-verify — `withoutGlobalScope('event_owner')` is indexer building cross-owner docs; needs ACL review, not auto HIGH.
- Filament occurrence/session/attendance/changelog inherit null-matrix above; same MEDIUM-verify.
Performance:
- Per-participant/answer/item `create()` loop in one txn MEDIUM — chunk or queue for 100+.
- No `paginate()` in `src` MEDIUM-verify — confirm cursor for list/search or large listings OOM. Check-in `lockForUpdate` GOOD.

---

## 3. Growth / Incentives

### promotions
Bugs:
- `DeactivatePromotion:14` MEDIUM — never sets `deactivated_at`.
- `MarkPromotionAsUsedOnOrderPlaced:106` MEDIUM — atomic `tryIncrementUsage` but no per-order dedup; redelivery double-counts.
- `PromotionService:209-274` MEDIUM — `per_customer_limit` fails open (no customer / missing orders pkg / catch → `true`).
- Global `code` unique LOW — blocks cross-owner reuse + enumeration oracle.
- `Promotion:366` LOW — `round()` vs voucher `intdiv(+5000,10000)` 1¢ drift.
Security:
- `PromotionPerformanceInsights:114-123,203-206` HIGH — owner-blind `Promotion::query()` + unscoped `Order::select()->get()`.
- `DeactivateExpiredPromotionsCommand:23-26` HIGH — cross-tenant sweep; iterate owners explicitly.
- `CreatePromotion/DeactivatePromotion` no `OwnerWriteGuard` MEDIUM — model `saving/updating` enforces only when `promotions.features.owner.enabled` (disabled by default).
Performance:
- `withinCustomerLimit` O(promotions×orders) HIGH — each candidate re-chunks entire order history `:251-263` + PHP JSON parse `:276-298`. Hot cart path.
- Insights full-table loads HIGH — ~8 aggregates + `pluck(all):149` + `Order::get(all):203-206`.
- `DeactivateExpiredPromotionsCommand get()` LOW — `chunkById` + owner iteration.

### vouchers
Bugs:
- `VoucherWallet:81-101` HIGH — `claim/markAsRedeemed` check-then-set, no txn/lock/`whereNull` → double redeem.
- `VoucherService:361-366` MEDIUM — percentage redeem records 0-value usage consuming `usage_limit`.
- `RecordVoucherUsage:78-90` MEDIUM — currency never validated vs voucher currency.
- `reserve()/release():257-338` MEDIUM — advisory cache dead code (zero readers).
- Idempotency gap MEDIUM — null key when neither `metadata.idempotency_key` nor `order_id` → plain `create()`, NULLs don't dedup.
- `VoucherValidator:153-171` MEDIUM — unknown cart shape totals 0 (fragile fail-closed).
- `AddVoucherToWallet` CORRECTED — unique `voucher_wallets_one_active_per_holder WHERE redeemed_at IS NULL` EXISTS; residual is concurrent 500s (no lock/23000 rescue), not silent duplicates. `VoucherService:216-232 addToWallet` skips even the `first` check.
Security:
- `UpdateVoucher:25-27` CRITICAL — `VoucherModel::where(code)->firstOrFail()` unscoped; `Voucher booted:596-638` has zero owner checks.
- `resolveRedeemedByOrder:405-407` MEDIUM — `Order::select()->find()` no `forOwner`.
- `removeFromWallet:244-248` MEDIUM — `VoucherWallet::where(voucher,holder)->delete()` no owner predicate.
- Per-user limit unscoped + guests skipped MEDIUM — `RecordVoucherUsage:67-76` counts with no owner scope; `Validator:88-102` only `Auth::user()`.
- `ExpireVouchersCommand withoutOwnerScope` LOW — needs explicit global context.
Performance:
- `getTimesUsedAttribute:393-406` N+1 MEDIUM — `usages()->count()` per voucher unless `withCount`.
- `scopeLive:254-274` correlated subquery per row MEDIUM — prefer `withCount+having`/join.
- `getUsageHistory:204-206` unbounded MEDIUM.
- `include_global=true` bypasses `VoucherLookupCache:36-38` LOW.
Good: `RecordVoucherUsage:43-50` txn+lock+recheck+unique; `ExpireVoucher` locks; targeting fails closed.

### affiliates
Bugs:
- `CreatePayout:36-100` CRITICAL — read-no-lock `get()` + create payout + conditional claim → overlapping ids paid twice.
- `UpdatePayoutStatus:40-58` HIGH — cancel/fail never refunds `available_minor` (refund lives in `PayoutReconciliationService:95-125`, never called here).
- `MatureConversion:26-48` HIGH — no txn/lock; concurrent matures double-release.
- `ApplyConversionAccounting:90-93` HIGH — unclamped `decrement(holding/lifetime)` → negative.
- `RecordAffiliateConversion:41-171` MEDIUM — no txn; idempotent only with `external_reference`.
- `ApplyConversionAccounting:36-49` MEDIUM — locks Affiliate row, mutates Balance lock-free → TOCTOU.
- `CommissionCalculator:12-22` + `RecordAffiliateConversion:179-181` LOW/HIGH — no rate cap; `payload.commission` trusted.
Security:
- `CreatePayout:65-66` HIGH — `$attributes['owner_*'] ?? conversion->owner_*` with no vs-context validation → re-home payout.
- Unsigned webhook MEDIUM — `WebhookDispatcher:32-33` null signature if secret empty; still queues/sends.
- Attribution cookie bearer MEDIUM — no session/cart fingerprint by default (opt-in `:234-260`).
- API `none` auth LOW — `EnsureApiAuthorized:15-18` disables auth via config; ensure never prod.
- Raw IP/UA PII LOW — persist without hashing/retention note.
Performance:
- `CommissionRuleEngine:$rulesCache` Octane-stale HIGH-verify — `private array` on `singleton(:109)`; verify binding scope (if `scoped`/request, downgrade).
- Report fan-out MEDIUM — per-slice queries; tier/promotion N+1; tenant-aware cache or eager-load.
Good: `ClaimScheduledPayout:62-168` reference impl (locks+FIFO+`operation_key` unique); referral redirect allowlisted.

### affiliate-network
Bugs:
- `ApplyToOffer:45-96` MEDIUM — check-then-create, no txn/lock/23000 rescue; race → 500 via `unique(offer_id,affiliate_id)`.
- `OfferLinkService:36-56` MEDIUM — arbitrary `target_url` stored; redirect only checks scheme → open redirect.
- Clicks/conversions gameable MEDIUM — raw `increment()`; no bot/dedup/idempotency.
- Counters/sync internals fillable MEDIUM (`OfferLink clicks/conversions/revenue`; `Offer source_checksum/last_synced_at`).
Security: `resolveLink:115-128` acceptable by design — explicit `withOwner(null)`, 64-bit `random_bytes` code, `signed` + `throttle:60,1`. `SiteContentFetcher:27-44` GOOD — `PublicHttpUrlGuard` + pinned client + timeouts + 1MB cap; strategies use `hash_equals`.
Performance: import bounded loop LOW — `OfferImportService:44-58` `array_slice(500)` loop, no chunk/cursor for large syncs.

### growth
Bugs:
- `ResolveExperimentAssignment:248-257` MEDIUM — `variantForSubject` skips `resolveExperimentForCurrentOwner` (verify callers).
- Multi-currency no FX MEDIUM — `MetricsCalculator:217-221` filters to experiment currency, drops rest silently.
Security: clean — signal binding `validateSignalReferences:410-450` + explicit-global gate; no `owner_*` fillable.
Performance:
- `AggregateExperimentMetrics:60-66` unbounded `get()` MEDIUM — route dashboards via bounded/`handleMany` UNION (`:230-327`).
- `pickVariant:259-266` re-queries per assignment LOW — cache per request.
- `handleMany` 3-query UNION + request cache GOOD.

### engagement
Bugs:
- follow/bookmark/react/respond HIGH — `first→create`, no lock, no composite unique (only counters unique) → twins skew counters.
- Reminder double-send HIGH — `markSent/Failed:111-127` no status precondition; `SendDueRemindersCommand:56-73` re-check non-atomic, no lease.
- `share_token Str::random(16)` indexed not unique MEDIUM → collisions.
- Unbounded reminder/subscription creation MEDIUM — no dedup/throttle.
- `BookmarkCollectionItem` dedup unverified — `firstOrCreate` without confirmed unique; verify migration.
Security:
- Actor/subject owner-equality MEDIUM/HIGH-verify — `DefaultEngagementPolicyResolver` all `true`; creates rely on ambient context. Confirm resolver.
- State/counter reads rely on global scope LOW — prefer explicit `forOwner`.
Performance:
- Per-type counter fan-out MEDIUM — `recalculateResponses/Reactions:228-275` per-type `count` + `updateOrCreate`.
- GOOD: `aggregateCounters:141-177` single round-trip; `dueReminders cursor()`; counters unique.

### feedback
Bugs:
- `SubmitFeedbackResponseAction:139-149` HIGH — `exists()` then insert, form lock only, no `(form,respondent)` unique → duplicates.
- Invitation-expiry rollback MEDIUM — `assertInvitationValid:170-173` marks `Expired` then throws inside same txn → rolled back.
- `StartFeedbackResponseAction:42-59` MEDIUM — unlimited drafts, flips invitation to `Started` with no status check.
- `DeleteFeedbackFormAction:20-46` MEDIUM — `get()->each->delete()` N+1.
- No sweeper LOW; raw token in path LOW (`InvitationUrlGenerator` emits `/feedback/invitations/{rawToken}`; mitigated by `token_hash` unique + rate-limit).
Security:
- Anonymous vs guard HIGH-verify — `SubmitFeedbackResponseAction:34-52` requires `OwnerWriteGuard`; link-only anonymous with owner-mode on 403s absent explicit-global wiring, or leaks via enumeration if bypassed. Needs explicit test.
- `ResolveFeedbackInvitationTokenAction` returns full unscoped model MEDIUM — `email/phone/metadata` + `token_hash` (no `hidden`); audit callers for serialization.
Performance: recalc fan-out MEDIUM — `FeedbackAnalyticsService:95-156` per-view queries on nearly every submit; no debounce. Submit eager-loads well (`with(questions.options):35-38`) GOOD.

---

## 4. Checkout flow

### cart
Bugs:
- `DatabaseStorage:683-724` HIGH — CAS insert `first()` + bare `insert()`, no 23000 retry on `(owner_scope,identifier,instance)` unique.
- `MigrateGuestCartToUserAction:107-125,184-214` HIGH — putItems/putConditions/putMetadata + markMerged + forget, no txn → duplicates → double-checkout.
- `CartModel:161-165` MEDIUM — `markAsConverted()` no version/lock, no already-converted guard.
- `Condition:227,404-411` MEDIUM (was HIGH "undocumented") — `"+5"`=5¢ vs `"+5.00"`=500¢ IS in docblock `:399-403`; unit-cliff concern, not undocumented.
- `ApplyStoredCondition:64-118` MEDIUM-HIGH (was HIGH) — `applyCustom()` no allowlist/range on `name/type/target/value/order` (rules `factory_keys` allowlisted `:78-81`).
- `DatabaseStorage:352-373` MEDIUM — `swapIdentifier()` unconditional target wipe; `has()` outside txn.
Security:
- `NormalizedCartSynchronizer:135-210` pattern note LOW (was MEDIUM exploit) — child `where(cart_id)->delete()/upsert()` unscoped, but parent resolved `forOwner(:51)` + `deleteNormalizedCart` re-scopes `:126-163`; not exploitable as stated. Harden with explicit scope.
- Abandoned-cart raw deletes safe only via owner iteration — `ClearAbandonedCartsCommand:90-94,311+` iterates tuples + `withOwner` per batch.
Performance:
- 3+ round-trips per cart MEDIUM (narrowed) — true for snapshot path (`save` + items `upsert:210` + conditions `upsert:277`); primary `carts` storage single-row JSON.
- CAS spin no backoff MEDIUM — `handleCasConflict:732-745` throws, no retry.
- Missing `(identifier,instance,version)` composite LOW.

### checkout
Bugs:
- `CheckoutService:78-91` HIGH unscoped `resumeCheckout` (see queue).
- `CheckoutFinalizer:29-33` MEDIUM (was HIGH "completed_at=null") — bypasses `CheckoutSession::transitionStatus()`; `completed_at=null` disproved (`updating:382-383` still sets it); residual is inconsistent persistence path.
- `CheckoutSession:183,204,236` HIGH lost-update on JSON blobs; nested txn `:436` inside `:107` lengthens locks.
- `CheckoutService:51-71` MEDIUM — no owner assertion on `startCheckout`; cart/customer unvalidated.
- `CreateOrderStep:148-217` MEDIUM — index-aligned pricing (`$pricingItems[$index]`); join on `item_id`.
- `CreateOrderStep:326-329` MEDIUM — `'unknown'` gateway/txn fallbacks collapse `(order,gateway,transaction)` idempotency.
- `PaymentCallbackController:188` residual LOW — "failure/cancel never matches" FALSE (identical gateway keys under success/failure/cancel still pass `array_key_exists`); residual: type-segment not verified.
Security:
- `CheckoutSession:80-116` HIGH mass assignment (see queue).
- `startCheckout:51-71` MEDIUM — session can be ownerless/global.
- Rate-limit-before-verify DoS MEDIUM — `PaymentCallbackController:129-137` `hit()` before `hash_equals`; hit after verify or per-IP+session keys.
- `CheckoutSession:302-308` raw PK update LOW — intentional (commented bypass of Spatie loop); document only. Validator fails closed GOOD.
Performance:
- 20–30 `UPDATE checkout_sessions` per pipeline HIGH — per step `setStepState` + `update(current_step)` + domain updates ×~11 steps. Coalesce or defer to end.
- Per-line pricing loop MEDIUM — `CalculatePricingStep:112-159` per-line `calculate()`; `resolvePriceable:194` unscoped `find` per line then owner-compare (correct reject, still 1 query/line).
- Gateway calls inside txn MEDIUM — `processCheckout:107` txn wraps `stepExecutor->run`; move I/O outside.

### orders
Bugs:
- `Order:399-409` HIGH — `recalculateTotals` tax-inconsistent (`subtotal=sum(tax-inclusive total)`, keeps `tax_total` separate, `grand=items+shipping-discount` drops tax); disagrees with `CreateOrderFromCart:40-50` by `tax_total`.
- `Order:380-384` HIGH — `getBalanceDue = grand-paid+refunded` (10000/10000/2000 → 2000 due, should be 0).
- `OrderPayment:222-241` MEDIUM — lock-free `exists()` TOCTOU; only `PaymentConfirmed:42-84` handles 23000.
- `CreateOrder:154-176` MEDIUM — strict `(string)===/(int)===` intake compare breaks whitespace-variant retry.
- `OrderItem saving:232-234` LOW — unconditional `total` overwrite, no clamp/quantity check.
Security:
- Mass assignment HIGH — `Order:105-134 owner_*/status/*_at`; `Payment:60-72`, `Refund:63-77`, `Item:68-87 status/*_at`.
- Child inherit when scoping disabled MEDIUM — fall back to unscoped `findOrFail` + inherit when `orders.owner.enabled` off.
- `findExistingIntake:333-340` LOW — `forOwner(includeGlobal)` oracle (conflict vs return reveals totals to guesser).
Performance:
- 4–5 `sum()` per balance check HIGH — single `SUM(CASE)` or cached columns.
- Row-by-row inserts + `fresh` in txn MEDIUM — 50 lines = 50+ inserts + selects under lock.

### shipping
Bugs:
- `CreateShipment:77` HIGH — `event(new ShipmentCreated)` inside txn, no `afterCommit` (orders uses `afterCommit:143-145`); ghost waybill on rollback.
- `ShippingRate:186-194` MEDIUM — fail-open `default => true`; should fail-closed.
- `ShippingRate:294` MEDIUM — `(int)(total*rate/10000)` truncation vs cart half-up (`CartMoney:51-61`).
Security:
- `OrderFulfillmentHandler:224` HIGH — `Shipment::where(tracking_number)->first()` no `forOwner`; enumerable tracking → disclosure. Scope or signed URL (copy jnt `AwbController`).
- `OrderFulfillmentHandler:288,345` HIGH — `location_id` via bare `InventoryLocation::find()`; ships from + leaks foreign warehouse.
- `Shipment:72-74 owner_*` fillable note — `CreateShipment:29-37` rejects mismatch (keep guard; remove from fillable). Label route is token+auth+owner-match (not `signed` — jnt AWB is the signed reference).
Performance:
- `RecalculateShipmentWeight:17-19` hydrates MEDIUM — use SQL `sum(weight*quantity)` (already used `CreateShipment:74`).
- Triple item scan MEDIUM — eager-load `items` once (copy `GenerateCheckoutDocumentsJob:72`).
- Driver fan-out MEDIUM (narrowed) — default parallel via `Concurrency`; serial only in fallback; no timeout/circuit in either. Add timeout/circuit + short-TTL cache.

### jnt
Bugs:
- `ProcessJntWebhook:493-500` HIGH — `CarbonImmutable::parse()` throws on bad `scanTime` in `usort` comparator + sync paths → retry loop.
- `JntTrackingEvent:80-106`, `Parcel:49-70`, `Item:50-74` HIGH — inherit-without-authorize (unscoped parent copy, no `OwnerContext::resolve()` check; contrast `cart/Condition:536-553`).
- Per-detail inserts MEDIUM — per-row `create()` + catch-unique-skip; use `upsert(event_hash)` (unique exists).
- Last-write-wins MEDIUM — `fill($updates)->save():461-462` no txn/lock.
- `JntWebhookLog:125-128` MEDIUM — hardcoded `'webhook_calls'`, ignores prefix/tables.
- `JntOrder:224-231` LOW — non-atomic cascade, no txn/chunk.
Security:
- `JntSpatieSignatureValidator:17-22` CRITICAL (narrowed) — `verify_signature=false` bypass with NO prod fail-closed (contrast checkout). Default `true` + empty-secret fails closed, so requires explicit opt-out.
- `JntTrackingEvent/Parcel/Item creating` HIGH (above).
- `WebhookController:75-103` MEDIUM — distinct 401 vs 422 oracle; use uniform `200+{code:0}` per J&T spec.
- `verifyAndParse:155-165` LOW — signs parsed `input('bizContent')` not raw `getContent()`; false negatives.
- `AwbController:17-53` GOOD reference — `hasValidSignature` + `OwnerSignedDownload` + `no-store`.
Performance:
- O(n log n) re-sort per webhook MEDIUM — J&T already chronological; single max-scan O(n).
- `latest('scan_time')` per order N+1 LOW — `latestOfMany`/subquery.

---

## 5. Payments / Docs / Comms / Analytics

### chip
Bugs:
- Float on minor units HIGH — `PurchaseDetailsData:96`, `ProductCollection:57,69`, `ProductData:105 (int)(amount*(float)qty)`; use half-up like `ChipIntegerModel:145`.
- `PurchasePaidHandler:27-29` MEDIUM — only `status=PAID`; never updates `paid_on/total_minor/payment_method`.
- `SyncPurchaseRefundState:56-63` MEDIUM — partial also `status=refunded` + `refunded_at` (no `partially_refunded` state).
- Refund sum-then-save race MEDIUM — no txn/lock.
- Duplicate in-flight webhooks both dispatch MEDIUM — `isDuplicate:97-110` only skips `processed`.
- `ChipCustomerDirectory:31-46` MEDIUM — `owner=null` adds no scope; callers must pass owner.
Security: verified safe — `verify_signature=false` throws in prod; secrets env + redaction; no server-side SSRF. `WebhookSimulator:154-156` disables verify test-only, acceptable.
Performance: indexes GOOD — `idempotency_key` unique, GIN metadata. No polling. Retry `usleep` is GET/HEAD/OPTIONS-only, never on mutations.

### cashier (no models by design)
Bugs:
- `StripeGateway:413-423` HIGH — re-encode `json_encode($payload)` breaks Stripe HMAC (needs raw body).
- `SyncWebhook:21-35` HIGH — never calls `verifyWebhookSignature()` (same for `WebhookReplayCommand:33,65`).
- `StripeGateway:147-150` MEDIUM — explicit `'amount'=>null` risks full-refund rejection (omit key).
- `retrievePayment/Invoice:218-261` MEDIUM — cross-owner returns null, indistinguishable from 404.
- `OwnerScopedQuery:17,73-80` LOW — static `hasColumn` cache never cleared; stale after migration under Octane.
Security: Stripe primitive correct but unenforced HIGH — `Webhook::constructEvent` + empty-secret→false GOOD, but callers never verify.
Performance:
- Remote N+1 HIGH — `invoices():297-315` per-invoice `asStripeInvoice()` API fetch.
- `subscriptions():279-289` no pagination LOW.

### cashier-chip
Bugs:
- No `(subscription_id,period_key)` unique MEDIUM-HIGH — `ClaimRenewalAttempt:21-69` SELECT-then-INSERT serialized only by subscription lock; crashed lease → double-bill.
- `WebhookCommand:55` MEDIUM — dead key `cashier-chip.webhooks.verify_signature` display-only; real switch in `chip`.
Security:
- `PaymentMethodStore:72-182` GOOD — scoped → `withoutOwnerScope` re-check → `AuthorizationException` on cross-tenant.
- `validate_billable_owner=false` kill-switch LOW.
- `RenewalAttempt` no owner columns MEDIUM — isolation via `belongsTo subscription` join only.
Performance: `ChipSubscription items` N+1 MEDIUM (corrected citation `:1158` with `loadMissing:1140`) — eager-load `items` at query site.

### docs
Bugs:
- `DefaultNumberStrategy:52` MEDIUM — `uniqid('',true)` time-based predictable numbers.
- `DocDownloadController:62-64` MEDIUM — sync Browsershot/Chromium in GET; enqueue + 202.
- `DocService:86-89` LOW — caller `pdfOptions` merge; mitigated (`DocRenderService:177-210` constrained keys).
Security: verified safe — share `random(48)`+SHA256, hash lookup + re-scope, uniform 404, `no-store/noindex`. `TiptapJsonRenderer:186-197` blocks `javascript:`/traversal. Residual LOW: allows `http://` embeds.
Performance: `SequenceManager`/`DocPaymentRecorder` locks GOOD; `loadMissing(template,docable)` GOOD. Sync Browsershot in GET is the perf cost (see bugs).

### communications (17 models)
Bugs:
- Tracking token HIGH — `CreateTrackingTokenAction:26-47` discards plaintext (`Str::random(64)`), persists only hash; `getToken()` returns hash-as-bearer; no redemption route.
- Inbound `findOrFail` without guard HIGH — `CreateTrackingTokenAction:26`, `AddCommunicationRecipientAction:25`, `RecordTrackingInteractionAction:26-28` trust global scope.
- `Communication:221-243` + `Delivery:201-208` MEDIUM — `chunkById(100)->each(delete)` ×6 with nested cascades → 1000s queries.
Security:
- Inbound IDs without guard HIGH (above).
- Attachment `disk/path/mime` mass-assignable MEDIUM-verify — `Attachment:47-60` fillable; no in-`src/` `mimes/max` (Filament-only = API bypass). Confirm server validation.
- `DestinationProtectorService:12-32` AES-CBC unauthenticated LOW-verify — confirm or use AEAD.
- Webhook ingress GOOD — HMAC+`hash_equals`+300s+abort-on-missing-secret; `__owner_*` strip + server re-resolve.
Performance: GOOD — queued webhooks/deliveries/notifications; `202` dispatch; configurable idempotency store. Delete amplification (bugs) is the perf cost.

### signals
Bugs:
- `IngestSignalEvent:260-262` MEDIUM-HIGH privacy — `'*'` allowlist skips PII blocklist.
- Browser events no idempotency MEDIUM — `idempotencyKey` trusted-only `:47-57`; retries duplicate rows.
- `revenue_minor (int)` cast LOW — truncates floats, accepts negatives.
Security:
- `write_key random(40)` bearer in query LOW — plaintext; sent as `data-write-key` + body/query → log leak. Prefer header/body.
- Trusted/browser ingestion GOOD — strict timestamp/replay/format/`hash_equals`/RateLimiter dedup; per-prop/IP limits.
Performance: GOOD — async geocode default; queued alert eval; reports eager-load; `(tracked_property,idempotency_key)` unique.

### csuite — metapackage, no `src/`/routes. No findings. Inherits bundled packages' findings.

---

## 6. Filament adapters (`filament-*`, 31 packages)

Global:

- G1 Navigation PASS — no static `$navigationGroup`, no flat key, no `Plugin::get()` delegation (except `filament-authz/RoleResource:149-162` badge delegation LOW). Non-standard sort key paths compliant but fragile.
- G2 `getNavigationBadge()` uncached COUNT per nav render MEDIUM — every resource. Copy `filament-docs/DocResource:134-154` (`OwnerCache` 30s + `SUM(CASE)`), `filament-orders/OrderResource:64-65` (`FilamentOrdersCache`), `filament-jnt/NavigationBadgeHelper:16-24` (auth+owner-keyed cache). Worst: `CartDashboard:48,55` double-counts same query.
- G3 N+1 `TextColumn relation.field` without `with()` MEDIUM — only `AffiliateLink/Touchpoint`, `PassTransfer`, ticketing `Pass` eager-load. Worst: `filament-affiliates` (7 resources), `filament-events`.
- G4 Unpaginated `Select::options(Model::pluck()->all())+preload()` MEDIUM — whole-table load. Use `relationship()+getSearchResultsUsing`. Clusters: affiliates, addressing, persons. GOOD: `filament-authz/UserAuthzForm:66-71` (`modifyQueryUsing`).
- G5 Domain leakage LOW/MEDIUM — `filament-vouchers/MoneyHelper` overlaps `MoneyNormalizer`; `VoucherSuggestionsWidget:223-228` re-implements math; `UplineVisualizationWidget` tree math belongs in core.
- G6 Collection sums — `filament-cashier-chip` now `withSum('items','unit_amount')` (`MRR:45,67,135`, `RevenueChart:99,113`); residual is 3–7 uncached `count()` per widget render MEDIUM. `filament-inventory/InventoryLocationInfolist:85,89,93` 3 queries per view MEDIUM — use `withCount/withSum`.

Per-package exceptions (false positives removed; see §7):

- `filament-addressing` MEDIUM — `AddressAreaResource:68-70` bare parent vs scoped siblings; `PostalCodeResource:89-94` areas filter only `country_code`.
- `filament-affiliate-network` HIGH*-verify → LOW if gate proven — 4 resources `withoutOwnerScope/withoutGlobalScope` gated only by `NetworkAdminAccess::allows()` + `admin_ability` config; confirm admin-only.
- `filament-affiliates` MEDIUM-HIGH — `AffiliatePayoutResource:80-83` unscoped affiliate options (enumeration; write re-validated). link/membership/ticket `relationship()` no `modifyQueryUsing`.
- `filament-authz` LOW/MEDIUM — `PermissionResource` bare (OK if global); `UserResource:47-50` only impersonation guard, no `OwnerUiScope` (confirm User ownership); impersonate POST-only + 403 GOOD.
- `filament-cart` — scoping exemplary. No badge `"0"` issue (resources return `null` when 0).
- `filament-cashier` MEDIUM — no resource `getEloquentQuery`; `InvoicesTable:79-84` external `pdfUrl` action IDOR if list unscoped. Add resource-level query.
- `filament-cashier-chip` MEDIUM perf — inheritance contract GOOD (`BaseCashierChipResource` + `assertResolvedOrExplicitGlobal`). `CustomerPortal/Invoices:59-73` safe iff `getBillable()` owner-bound — verify else HIGH.
- `filament-chip` MEDIUM — `BaseChipResource:17-27` silent unscoped fallback (should `whereRaw('0=1')` like shipping). Statement `redirect()->away(download_url)` no action-level re-validation MEDIUM.
- `filament-commerce-support` OK. `filament-communications` GOOD (all 7 scoped). `filament-contacting` GOOD. `filament-customers` GOOD exemplary. `filament-docs` GOOD exemplary + cached badge.
- `filament-engagement` GOOD — all 7 `OwnerUiScope+with()`.
- `filament-events` MEDIUM-verify — `EventRegistrationParticipant:50-56` only `whereHas(event)` + unguarded badge; Venue/Space/Taxonomy/Term no query (confirm global vs owner, else HIGH).
- `filament-feedback` GOOD — all 5 scoped, exports scoped.
- `filament-growth` GOOD — scoped + constrained eager; variant form delegates to `scopeAccessibleExperiments`.
- `filament-inventory` — resources `with()+OwnerScope` GOOD (only package consistent). Infolist per-row aggregates + action `location_id` scope unverified (tables/forms scoped, confirm actions).
- `filament-jnt` GOOD pattern — `BaseJntResource` + owner-keyed cached badge + `OwnerUiScope`.
- `filament-orders` GOOD exemplary — `throttle+FilamentAuthenticate`, 404 when owner unresolved, `forOwner+findOrFail`, `Gate::allows('view')` download.
- `filament-persons` — bare `with()` correct (Person explicitly unscoped shared identity per `filament-persons/CONTEXT.md`); relation selects no `modifyQueryUsing` MEDIUM (enumeration scope only).
- `filament-pricing` GOOD — owner-gated + `OwnerQuery` resolution + delegates to `PriceCalculatorInterface`.
- `filament-products` MEDIUM — writes exemplary (`OwnerScopedIds` create/edit + bulk). `ProductsTable:91,99` per-row `prices()->count()` N+1 + CSV import category re-check gap.
- `filament-promotions` LOW — scoped + `withCount(usages)` GOOD; uncached badge.
- `filament-seating` GOOD — scoped + `withCount(sections)`.
- `filament-shipping` — Shipment/Return/Zone strip-then-`forOwner` + `whereRaw('0=1')` secure; `ShippingRateResource:55-56` scopes via `whereHas(zone forOwner)`. Widgets `withoutGlobalScope` without visible re-scope (confirm `OwnerContext::resolve()`).
- `filament-signals` GOOD — all `forOwner()->with()` + `SignalsModelReferenceGuard`.
- `filament-tax` GOOD exemplary polymorphic — scoped + relationship options scoped + create/edit re-validate only if target `HasOwner`.
- `filament-ticketing` GOOD — all `OwnerUiScope+whereHas(pass)+with()`. Verify `TicketTypeResource:71` `MorphToSelect ticketable` per-type scoping LOW.
- `filament-vouchers` LOW-if-gate / HIGH-if-bypass — `VoucherForm:395-436` editable `owner_type/id` mitigated by `enforceOwnerOnCreate/Update` + `VoucherAffiliateOwnershipGuard` when `owner.enabled`; confirm UI hides Ownership when disabled. Badges uncached. Activate/Pause/Redeem all `OwnerWriteGuard` GOOD.

---

## 7. Removed as FALSE (do not re-file)

- customers `LinkCustomerToPerson` unscoped (persons global by design; customer side guarded).
- pricing nested-txn + `price_list_id` scope (both correct).
- inventory `createOrFirst` no-unique (unique `reference,owner_type,owner_id` exists + txn).
- seating `seat_holds` missing owner cols (has `nullableMorphs('owner')`).
- events `(float)price` (no file:line located).
- events `EloquentEventSearchEngine:24` unscoped leak (inherits `HasOwner` global scope).
- affiliates `findByCode` unscoped (applies `forOwner` + `requireOwnerContext`).
- affiliates `MatureConversion` missing `approved_at` (auto-set `AffiliateConversion:200-218`; lock-free part kept).
- affiliate-network `$syncingImport` leak (has `try/finally` reset).
- vouchers `AddVoucherToWallet` silent duplicates (unique `voucher_wallets_one_active_per_holder WHERE redeemed_at IS NULL` exists; residual is 500s).
- authz `Permission.php` teams-key (no teams code there; Role half kept).
- addressing silent coercion of explicit ids (throws on mismatch).
- feedback `CalculateFeedbackResponseScore` unscoped (uses `OwnerQuery::applyToQueryBuilder`).
- feedback analytics job ownerless (implements `OwnerScopedJob` + `withOwner`).
- cart `NormalizedCartSynchronizer` UUID-enumeration wipe (parent `forOwner` + re-scope; kept as LOW pattern note).
- cart `CartOwnerScope` null-fragile (delegates to shared `OwnerQuery`).
- checkout callback never-match (gateway keys identical under success/failure/cancel; residual LOW type-segment).
- shipping Octane memo leak + missed zone invalidation (`$app->scoped()` + owner-keyed cache + zone clears cache like rates).
- shipping label "signed" (is token+auth+owner-match, not signed — still safe; jnt AWB is the signed reference).
- chip `usleep` on mutating path (only GET/HEAD/OPTIONS retry).
- cashier-chip duplicate claim impl (delegates to `claimRenewalAttempt->handle`; helpers only).
- cashier-chip duplicate N+1 citation (wrong lines; real map `:1158` with `loadMissing:1140`).
- docs `DueDocReminders` unscoped (uses `forCurrentOwner`; intentional owner enumeration).
- filament-persons HIGH cross-tenant list (Person explicitly unscoped shared identity; bare query correct).
- filament-organizations user-options unscoped (verified limited to `$record->members()`).
- filament-promotions missing `OwnerWriteGuard` (uses it `:124-130` when owner enabled).
- filament-cashier-chip `get()->sum(fn)` (now `withSum`; residual uncached `count()` only).
- filament-shipping key-drift/rate-scope (both proved scoped: core `shipping.features.owner.*`; `ShippingRateResource` scopes via `whereHas(zone forOwner)`).
- filament-cart badge `"0"` (resources return `null` when 0; only `CartStatsWidget:93` rate string).
- filament-affiliates `FraudSignal:198` unscoped badge (re-applies `OwnerQuery:200-208` when owner enabled).
- growth `handle():60-66` as bug (kept as perf only).
- inventory exports unpaginated (all use `cursor()`).
- pricing fan-out exact N unmeasured (direction kept).
- ticketing `get()->sum()` HIGH → MEDIUM (only `getTotalAvailable`; `getTotalOnHand` already SQL).
- shipping serial-only fan-out (default parallel via `Concurrency`; serial only in fallback).

---

## 8. How to verify (per-package, not repo-wide)

- DB: `rg -n -- "constrained\(|cascadeOnDelete\(" packages/*/database` (expect empty).
- Filament nav: `rg "static.*\$navigationGroup" packages/filament-*/src` (expect empty); `rg "'navigation_group'" packages/filament-*/config` (expect empty).
- Owner: `rg -n -- "withoutOwnerScope\(|withoutGlobalScope\(.*Owner" packages/<pkg>/src`; `rg -n -- "::query\(|->query\(|getEloquentQuery\(" packages/<pkg>/src`; `rg -n -- "count\(|sum\(|avg\(|exists\(" packages/<pkg>/src`; `rg -n -- "DB::table\(" packages/<pkg>/src`.
- Tests: `./vendor/bin/pest --parallel path/to/Test.php` per package; PHPStan: `./vendor/bin/phpstan analyse packages/<pkg>/src --level=6`.

## Limitations

- Read-only; no runtime/prod-data confirmation. Items marked verify need one follow-up read (named in text).
- Route-model-binding risk deferred to host app where packages expose no `routes/` (events has none; only affiliates/network/docs/communications/checkout/chip/jnt/shipping/signals/filament-authz/filament-docs define routes).
- Prior per-package `audits/*.md` hold historical migration context; severities here are verified code findings, not migration status.
