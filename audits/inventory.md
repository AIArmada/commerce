# Inventory Audit

## Packages Reviewed (bullets)

- `packages/inventory` (`aiarmada/inventory`) — domain owner: multi-location stock (levels/movements/allocations/batches/serials), costing (FIFO/weighted/standard), replenishment, reservations, cart/orders/payment integrations.
- `packages/filament-inventory` (`aiarmada/filament-inventory`) — Filament v5 admin adapter: 6 resources, 8 stock actions, 9 widgets, 3 policies.

Source layout inspected: `src/` (Actions ×16, Cart ×3, Console ×2, Contracts ×6, Data ×2, Enums ×13, Events ×13+, Exceptions ×4, Exports, Facades ×2, Health, Integrations, Listeners ×5, Models ×16, Reports ×3, Services ×13, States ×16, Strategies ×4, Support ×6, Traits ×3), `config/inventory.php`, `config/filament-inventory.php`, `database/migrations` (16), `database/factories` (13), `composer.json` × 2, providers, `CONTEXT.md`/`README.md`/`docs`. No `routes/`, no `tests/` in either package (verified — 13 factories with zero test consumers).

## Overall Assessment (quality, health, risks, refactor size)

The strongest engineering of the four pairs where it counts for money-adjacent stock moves: allocation paths use `DB::transaction()` + `lockForUpdate()`, owner scoping has a dedicated `InventoryOwnerScope` applied in services/reports/resources, and app-level cascades replace DB constraints per repo rules. But the package is oversized for its integration surface and carries three correctness-grade defects: (1) filament serial UI filters/writes enum string values (`'available'`) against a column storing spatie state morphs (FQCNs) — badges/filters match nothing and form saves write invalid states; (2) a dual int+decimal quantity system where the decimals are never read or written by any service; (3) a schema alteration for `allocations.reservation_group_id` hidden inside the reservations migration plus a missing `operations` config key. Refactor size: M (one status-vocabulary unification + one migration set + deletions of dead quantity columns + filament query fixes). No redesign of the allocation/costing core — it is sound.

## Migration Impact

**Migration Required: YES**

| Table | Change | Type |
|---|---|---|
| `inventory_allocations` | Add `reservation_group_id` (`foreignUuid()->nullable()` + index `inv_allocations_reservation_group_idx`) via a NEW dedicated migration; remove the hidden `Schema::table` alteration block from `2026_07_12_000002_create_inventory_reservations_table.php` (lines ~33-46). Both guarded by `hasColumn`/`hasIndex`, so already-migrated DBs are unaffected and fresh installs get the column from the right file. | Schema migration (new file) + edit of existing migration file (safe: alteration was conditional/idempotent). |
| `inventory_levels` | Drop `quantity_on_hand_decimal`, `quantity_reserved_decimal` (A3 — zero readers/writers in `src/`); drop/keep `unit_conversion_factor` (keep: part of UoM feature, even if lightly used). | Schema migration (drop columns, guarded). |
| `inventory_operations` | No schema change (table OK). Config gains the missing `operations` key (A1, code-only). | — |
| Other 13 tables | No change. | — |

No constraint changes (already constraint-free per rules), no data migration (decimal columns were never populated by services — verify with `SELECT COUNT(*) … WHERE … NOT NULL` pre-deploy; if any rows carry values, that itself is a bug to reconcile before dropping).

## Package Responsibilities

- Owns: stock levels/movements/allocations per location, batch/FEFO + serial lifecycle, costing layers + valuation snapshots, backorders, demand forecast + reorder suggestions, checkout reservations, cart/checkout/orders/payment event wiring.
- Does NOT own: product catalog (products, via `Inventoryable`), order lifecycle (orders, via events), fulfillment routing UI (shipping), admin UI (filament-inventory).
- Filament adapter owns: location/level/movement/allocation/batch/serial CRUD, transfer/receive/ship/adjust/cycle-count actions, stock widgets. Must stay UI-only (violations: serial enum vocabulary, stats aggregation duplication).

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)

### A1 — Filament serial UI speaks enum values; the column stores state morphs (CRITICAL)

- Severity: Critical
- Location: domain `src/Models/InventorySerial.php:15,77,201-368` (spatie `HasStates`, `States\SerialStatus`, `normalize()` returns FQCN morphs); `src/States/SerialStatus.php:57` + subclasses (`Available`, `Reserved`, `Sold`, …); vs adapter `packages/filament-inventory/src/Resources/InventorySerialResource.php:14,89`, `Tables/InventorySerialsTable.php:8`, `Schemas/InventorySerialForm.php:8` — all import `AIArmada\Inventory\Enums\SerialStatus` (string values `'available'`, `'reserved'`, …).
- Problem: `getNavigationBadge()` filters `where('status', 'available')` while the column holds e.g. `AIArmada\Inventory\States\Available` → badge always 0/empty. Table filters on the same values match nothing. The form select writes `'available'` into a state-cast attribute → spatie throws or stores garbage on save. The enum ALSO duplicates `label()/color()/isAllocatable()/isInStock()` already defined on the state classes.
- Why It Matters: Serial admin is non-functional for status workflows (the core of serial tracking); writes may corrupt the column.
- Recommended Fix: Unify on the spatie states (they carry the transition graph, which the enum lacks). (1) Change the three filament files to import `States\SerialStatus` + subclasses and use `::class`/`getMorphClass()` values with `SerialStatus::options()`-equivalent built from state classes. (2) Delete `src/Enums/SerialStatus.php` after grep-confirming no other users (only the three filament files import it — verified). Re-check (hardening pass): the audit's BackorderStatus half of this fix is REMOVED — no `src/Enums/BackorderStatus.php` exists and repo-wide grep finds zero `Enums\BackorderStatus` importers (`InventoryBackorder` already uses `States\BackorderStatus` exclusively); there is nothing to unify there.
- Breaking Change: YES (enum deletion; status filter values change from strings to morphs).
- Affected Packages: `filament-inventory` (3 serial files).
- Required Dependent Changes: `filament-inventory` serial resource/table/form; docs `04-usage.md` serial-status examples.
- Migration Required: NO (column values already morphs; only readers/writers change).

### A2 — Dual quantity system: decimal columns are write-/read-dead

- Severity: Medium
- Location: `database/migrations/2000_09_01_000002_create_inventory_levels_table.php:21,23` (`quantity_on_hand_decimal`, `quantity_reserved_decimal`, `decimal 15,4`); `Models/InventoryLevel.php:36-37,89-90,408-409` (docblock/fillable/casts); zero references in `src/Services`, `src/Actions`, `src/Reports`, `src/Cart` (verified — only model + migration mention them).
- Re-check (hardening pass): confirmed zero service/cart/report readers or writers; demoted High → Medium — dormant columns with no current divergence (locks cover the int path only), i.e. maintainability/schema hygiene, not an active correctness defect.
- Problem: Two quantities per level (int + decimal) with no sync rule, no documented canonical, and no code path populating the decimals. Any future reader must guess; any future writer splits the truth.
- Why It Matters: Stock truth must be single; phantom columns invite divergent writes under concurrency (locks in `InventoryService` cover the int path only).
- Recommended Fix: Canonicalize on the int columns (all services/transactions/locks already do). Drop the two decimal columns via migration (Migration Impact). Keep `unit_conversion_factor` (UoM feature, separate concern). If fractional quantities are ever real, that is feature work with lock-aware arithmetic — not a dormant column.
- Breaking Change: YES (columns removed) — zero in-repo readers/writers; pre-deploy null-check per Migration Impact.
- Affected Packages: `filament-inventory` (verify level form/infolist do not display the decimals — grep before landing; remove if present), `products` (reads int availability helpers — unaffected).
- Required Dependent Changes: filament level form/infolist cleanup if they reference the decimals.
- Migration Required: YES (drop columns).

### A3 — Reservations migration secretly alters the allocations table

- Severity: Medium
- Location: `database/migrations/2026_07_12_000002_create_inventory_reservations_table.php:33-46` (`Schema::hasTable`/`hasColumn` guards + `Schema::table` adding `reservation_group_id` + index); consumed by `Models/InventoryAllocation.php:68,123`, `Models/InventoryReservation.php:75`, `Services/Stock/CheckoutReservationService.php:79`, `Services/Stock/InventoryAllocationService.php:287,374,435`.
- Re-check (hardening pass): alteration block verified at `:37-39` with idempotency guards; demoted High → Medium — the column lands correctly on all install orders, so this is migration locatability/audit hygiene, not a runtime defect.
- Problem: Schema change for table B hidden in table A's migration. Fresh installs, `--pretend`/sql dumps, and migration audits all misattribute the column; rollback of one file leaves the other inconsistent. (The guards make it idempotent, which is why it "works" — that does not make it locatable.)
- Why It Matters: The next allocations change will be written in the wrong file again; `migrate:status`/squash tooling reasons per-file.
- Recommended Fix: New dedicated migration `…_add_reservation_group_id_to_inventory_allocations_table.php` with the same guards; delete the `Schema::table` block from the reservations migration. Both orders (old DBs, fresh DBs) converge (Migration Impact).
- Breaking Change: NO.
- Affected Packages: none.
- Required Dependent Changes: none.
- Migration Required: YES (new file; see block).

### A4 — `operations` config key missing though model + migration read it

- Severity: Medium
- Location: `config/inventory.php:7-23` (`$tables` has 15 keys incl. `reservations` but no `operations`); `Models/InventoryOperation.php:59-61` reads `inventory.database.tables.operations`; migration `2026_07_12_000001…:13,30` reads the same.
- Problem: Works only via the hardcoded `'inventory_operations'` fallback. Anyone remapping table names (the documented purpose of the `$tables` array) silently misses this table; `config('inventory.database.tables')` iteration (docs tooling, prefix renames) skips it.
- Why It Matters: Table-prefix feature is half-wired; rename migrations would orphan this table.
- Recommended Fix: Add `'operations' => $tablePrefix.'operations'` to the `$tables` array. No migration (name unchanged).
- Breaking Change: NO. Affected: none. Migration: NO.

### A5 — Redundant double owner-scoping on stock reads (global scope + location `whereHas` on every query)

- Severity: Medium
- Location: `Support/InventoryOwnerScope.php:62-88` (`applyToQueryByLocationRelation`, `applyToMovementQuery` with OR-ed `fromLocation`/`toLocation`); every `Services/InventoryService.php` read (`:131,197,300,322,340,382,434-439`), reports, and filament resources layer it on top of the models' own `HasOwner` global `OwnerScope`.
- Problem: `InventoryLevel`/`InventoryMovement`/`InventoryAllocation` all boot a global `OwnerScope` on their OWN `owner_*` columns AND get filtered by location ownership. The `saving()` hooks copy location owner onto the row (`InventoryLevel::booted`, movement/allocation equivalents — verified for levels), so the two predicates agree — but every list/report/checkout-availability query pays a `whereHas`/`orWhereHas` join pair on top of the global scope, and any future row whose direct owner drifts from its location owner becomes invisible with no error.
- Why It Matters: Checkout availability (`getAvailability`, `getTotalAvailable`, `hasInventory`) and allocation reads run these on the hot path; the OR-ed movement scope defeats index-prefix use on large `inventory_movements`.
- Recommended Fix: Keep the global `OwnerScope` as the enforcement point (it is the monorepo contract). Demote the location-relation scope to a write-time invariant (already enforced in `saving()`) plus a scheduled reconciliation report — remove it from read queries in `InventoryService` availability paths and filament list queries. Where cross-location reads genuinely need location scoping (movements between owners), keep `applyToMovementQuery` but add a composite index on `movements(from_location_id, to_location_id, created_at)` in the same migration release.
- Breaking Change: NO (predicates agree by invariant; behavior identical while invariant holds).
- Affected Packages: `checkout` (`ReserveInventoryStep`, `InventoryAdapter` — faster availability, same results), `filament-inventory` (list queries).
- Required Dependent Changes: add the reconciliation check to `Console/CreateValuationSnapshotCommand` or a health check (see A9) so drift is detected rather than silently hidden.
- Migration Required: YES only for the optional movement index (bundle with the A2/A3 migration release).

### A6 — Filament resources bypass `parent::getEloquentQuery()`

- Severity: Medium
- Location: `filament-inventory/src/Resources/{InventoryLevelResource:42-48, InventoryAllocationResource:39-45, InventoryLocationResource:43-46, InventorySerialResource:45-…, InventoryBatchResource:43-…, InventoryMovementResource:40-…}::getEloquentQuery()` — all build `Model::query()` directly instead of `parent::getEloquentQuery()`.
- Problem: Skips Filament's own query customization (tenant scope, soft-delete scope if ever added, plugin query modifiers). Sibling adapters (`filament-pricing`, `filament-promotions`, `filament-vouchers`) all use `parent::`. When owner mode is OFF, these resources return fully global queries with no panel-level scoping hook.
- Why It Matters: Inconsistent with every sibling adapter; future Filament tenant integration silently does nothing here.
- Recommended Fix: `parent::getEloquentQuery()` + existing `InventoryOwnerScope` application in all six resources (mechanical, mirrors `filament-pricing`).
- Breaking Change: NO. Affected: none. Migration: NO.

### A7 — `InventoryStatsAggregator` (filament) duplicates domain reporting

- Severity: Medium
- Location: `filament-inventory/src/Services/InventoryStatsAggregator.php` (`overview()`, `movementStats()`, `lowInventoryCount()`, `outOfStockCount()`, own `cached()` with TTL from `filament-inventory.cache.stats_ttl`) vs domain `inventory/src/Reports/{InventoryKpiService, StockLevelReport, MovementAnalysisReport}`.
- Problem: Two low-stock/out-of-stock/movement-stat implementations with independent queries and independent caching. Definitions can drift (threshold source, owner scoping, location vs direct scope) while dashboards cite both.
- Why It Matters: "Low stock: 12" (widget) vs "Low stock: 9" (report) destroys operator trust.
- Recommended Fix: Reimplement aggregator methods as thin calls into `StockLevelReport`/`MovementAnalysisReport`/`InventoryKpiService` (constructor-inject, keep the filament-side `cached()` TTL wrapper). No new queries of its own.
- Breaking Change: NO. Affected: none (numbers should converge; any change is a bug fix). Migration: NO.

### A8 — `HasInventory` trait is a cart-coupled god trait on catalog models

- Severity: Medium
- Location: `src/Traits/HasInventory.php` (20+ methods: levels/movements/allocations relations, `receive/ship/transfer/adjust`, `allocate/release/getAllocations(string $cartId)`, `getInventoryHistory`, `isLowInventory`, …) consumed by `products` models.
- Problem: Catalog models inherit cart concepts (`$cartId` allocation methods) and write paths (`receive/ship/transfer`) alike; every products-model change risks stock behavior and vice versa. The trait also duplicates `InventoryService` signatures one-to-one (pure forwarding for most methods).
- Why It Matters: Boundary erosion — `products` cannot evolve without auditing stock side effects; new devs call `$product->ship()` instead of the transactional service explicitly.
- Recommended Fix: Split: keep read-only relations + availability helpers (`inventoryLevels/Movements/Allocations`, `getAvailability`, `hasInventory`, `isLowInventory`) in `HasInventory`; move mutating + cart-coupled methods (`receive/ship/transfer/adjust/allocate/release`, `receiveAtDefault/shipFromDefault`) to `Cart/`-adjacent integration or require explicit `InventoryService` injection at call sites. Update `products` models + docs in-pass (mechanical).
- Breaking Change: YES (trait method removal) — consumers are `products` models + their callers; update in-pass.
- Affected Packages: `products` (trait consumers), `checkout`/`cashier` (if they call trait mutators — grep and migrate to `InventoryService`).
- Required Dependent Changes: migrate trait-mutator call sites to `InventoryService`; update `docs/04-usage.md` examples.
- Migration Required: NO.

### A9 — Operations/reservations bookkeeping has no observable reconciliation

- Severity: Low
- Location: `Models/InventoryOperation.php` (written only by `Listeners/DeductInventoryFromOrder.php`, `ReleaseInventoryFromOrder.php`), `Models/InventoryReservation.php` + `Services/Stock/CheckoutReservationService.php`, `Exceptions/InvalidReservationTransition.php`, `ReservationReferenceConflict.php`.
- Problem: `inventory_operations` (order_id/kind idempotency) and `inventory_reservations` (TTL + `expires_at`) accumulate terminal rows; `CleanupExpiredAllocationsCommand` covers allocations only; nothing cleans expired reservation groups or reconciles operations vs actual movements. `keep_expired_for_minutes = 0` in config suggests aggressive cleanup intent that the command set does not implement for the new tables.
- Why It Matters: Slow table growth + unreconciled reservation-vs-movement drift.
- Recommended Fix: Extend the existing cleanup command (or add `inventory:cleanup-reservations`) to delete expired/fulfilled reservation groups past TTL + report operations without matching movements via the `Health/LowStockCheck.php` pattern (add an `OrphanedReservationsCheck`). Document retention in `docs/03-configuration.md` (`cleanup` section).
- Breaking Change: NO. Affected: `orders`/`checkout` (read-only reconciliation). Migration: NO.

### A10 — Costing registry uses anonymous classes; strategies partially wired

- Severity: Low
- Location: `InventoryServiceProvider.php:193-292` (three anonymous `CostingMethodInterface` adapters + `AllocationStrategyRegistry` with `FefoStrategy`, `NearestLocationStrategy`); `config/inventory.php:53` (`allocation_strategy => 'priority'` — no `priority` strategy registered; registry holds fefo + nearest only).
- Problem: (a) Anonymous adapters are undebuggable in `artisan`/stack traces and untestable in isolation — extract to named classes. (b) Default config names a strategy (`priority`) the registry cannot resolve — verify the resolution path falls back sanely or misconfigures allocation order.
- Why It Matters: (b) especially: allocation order directly decides which location ships.
- Recommended Fix: Name the three adapters (`FifoCostingMethod`, `WeightedAverageCostingMethod`, `StandardCostingMethod` in `Services/Costing/`); reconcile `allocation_strategy` default with the registry (register the missing `priority` strategy or change default to `fefo` + document). Add a provider test asserting every configured strategy name resolves.
- Breaking Change: NO (unless default changes behavior — document if `priority`→`fefo` alters order; prefer registering `priority` to keep behavior).
- Affected Packages: `checkout`/`shipping` (allocation order consumers — behavior-preserving if `priority` is registered, not renamed).
- Migration Required: NO.

## Code Quality Findings (same finding format)

### C1 — `InventoryTraitsUsage.php` + `ExportRegistry`/`ReportRegistry`/`CostingMethodRegistry` ceremony

- Severity: Low
- Location: `src/Support/{InventoryTraitsUsage, AllocationStrategyRegistry, CostingMethodRegistry, ExportRegistry, ReportRegistry}.php`.
- Problem: Registry-per-concept with near-identical register/resolve shapes; `InventoryTraitsUsage` appears to be a usage-audit helper rather than runtime code — verify and delete if so.
- Why It Matters: New concepts copy the heaviest pattern instead of the lightest.
- Recommended Fix: Delete `InventoryTraitsUsage` if unused at runtime (verify via grep); leave the functional registries (they back real extension points: costing methods, allocation strategies, exports, reports).
- Breaking Change: NO. Migration: NO.

### C2 — `HasLocationHierarchy` + `LocationTreeService` overlap

- Severity: Low
- Location: `src/Traits/HasLocationHierarchy.php` vs `src/Services/Stock/LocationTreeService.php:122,216,228` (transactional create/reparent/rebuild).
- Problem: Tree mutation in two places (trait helpers vs service) — verify all writes funnel through the service (which holds the transactions); demote trait methods to read-only navigation (`ancestors/descendants/children`) if any write.
- Why It Matters: Hierarchy corruption under concurrency.
- Recommended Fix: Audit + move any trait writes into `LocationTreeService`; pin with a reparent test.
- Breaking Change: NO (if trait had no external writer callers — verify). Migration: NO.

### C3 — Decimal casts without decimal columns anywhere else + float coordinates

- Severity: Low
- Location: `InventoryLevel::casts()` (`decimal:4`), `InventoryLocation` `coordinate_x/y/z` (`decimal 10,2`, nullable).
- Problem: After A2, no `decimal:*` casts remain on levels — clean. Coordinates as decimals are fine; just note `NearestLocationStrategy` does Euclidean math on nullable columns — null-coordinate locations must sort last explicitly (verify), else arbitrary fulfillment routing.
- Why It Matters: Fulfillment location choice (with `shipping`) must be deterministic.
- Recommended Fix: In `NearestLocationStrategy`, push null-coordinate locations last (`orderByRaw('coordinate_x IS NULL, …')`) + test.
- Breaking Change: NO. Affected: `shipping` (`OrderFulfillmentHandler` consumes `FulfillmentLocationService`). Migration: NO.

## Laravel-Specific Findings

- PHP 8.4: PASS (`^8.4` in `composer.json`; enums, readonly, promotion used).
- PKs: PASS (all 16 migrations `uuid('id')->primary()`).
- FK constraints/cascades: PASS — `foreignUuid()` throughout with zero `constrained()`/`cascadeOnDelete()` (verified); cascades are app-level (`InventoryLevel::deleting` → allocations; `InventoryLocation` hierarchy; `Voucher`-style deletes N/A). Compliant.
- `down()` methods present everywhere although not required — harmless, keep. Exception: the reservations migration's `down()` (verify it drops the added allocations column too — if not, add it for symmetry with the extracted migration owning both directions).
- Owner scoping: strongest of the four pairs — `InventoryOwnerScope` (location-scoped + movement-scoped + cache-key suffix honoring owner) applied in services, reports, console command, and filament resources; models carry `HasOwner` + config key `inventory.owner`; `saving()` hooks bind rows to their location's owner with mismatch rejection. Gaps are A5 (redundancy cost, not a hole) and A6 (panel-scope bypass).
- Config: PASS structure (Database → Defaults → models/integrations → cart/payment/orders → events → cleanup) + required `json_column_type`; violations: missing `operations` key (A4), `allocation_strategy` default unresolvable (A10b). Filament config: PASS (nested nav, feature flags, resource sorts, cache TTL).
- Money: N/A for stock counts (ints, correct); costing/valuation in minor units — PASS (no `number_format` money composition in domain; the only `number_format` hits are console display + report percentages — acceptable).
- Events/listeners: cart (`CartCleared/Destroyed`, `ItemAdded`), payment (`cashier-chip` + `cashier` `PaymentSucceeded` + custom list), orders (`InventoryDeductionRequired/ReleaseRequired`) — all `class_exists`-guarded with config kill-switches. Exemplary optional-integration wiring; use as the monorepo reference.
- Console: `CleanupExpiredAllocationsCommand` + `CreateValuationSnapshotCommand` registered via package-tools `hasCommands` — correct. Owner scoping applied in cleanup (verified import) — confirm the snapshot command scopes snapshots the same way.

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction)

- A1 (serial/backorder vocabulary) is the critical domain leak — UI-defined enum diverging from domain states.
- A6 (parent query bypass) and A7 (stats duplication) above.
- Policies (`Policies/Inventory{Level,Allocation,ReorderSuggestion}Policy.php`, registered in `FilamentInventoryServiceProvider::packageBooted`) — verify each revalidates owner server-side (not just role checks); movement/batch/serial/location resources have NO policies — confirm intentional (read-heavy resources) or add consistent coverage. Low.
- Actions (`Actions/*` ×8) correctly delegate to domain Actions (`TransferInventory::run`, `ReleaseStock`, `ApproveReorderSuggestion::run` — verified imports) — thin, keep. Verify each revalidates submitted `location_id` owner-scope server-side (the domain `saving()` hooks are the backstop, but action-level `OwnerWriteGuard`/`ResolveOwnedModelOrFailAction` is the contract).
- Widgets ×9 with per-feature flags + `stats_ttl` cache — good shape; after A7 the stats widgets read domain reports and only format.
- Navigation: PASS — nested `navigation.group` (`Inventory`), `getNavigationGroup/Sort` from config on all six resources (verified), no static `$navigationGroup` in `src`.
- Dependency direction: PASS — `filament-inventory` requires `inventory`; domain references no Filament classes (verified — no `Filament\` imports under `packages/inventory/src`).

## Database Findings

- 16 tables, uuid PKs, `nullableUuidMorphs`/`nullableMorphs` owner tuples with explicit owner indexes, `timestampsTz`, no constraints — fully rule-compliant.
- Index coverage is good (status/date/owner/allocatable/expiring). Gaps: (a) allocations `reservation_group_id` index handled in A3's extracted migration; (b) optional movement composite for A5's retained cross-location reads; (c) `inventory_levels`: verify composite `(location_id, inventoryable_type, inventoryable_id)` exists for the locked `getOrCreateLevel` path (`InventoryService.php:136,202` `lockForUpdate()->first()`) — if missing, add unique (location + inventoryable) to make get-or-create race-safe; that would be a correctness upgrade worth a YES line in the same release (check current migration first).
- `quantity_on_hand > max_stock` raw check (`InventoryLevel.php:355`) — fine.
- Serial history/batches/cost layers/valuation snapshots/backorders/demand/leadtimes/reorder tables: no findings beyond A2's column drops.

## Model / Domain Findings

- `InventoryService` (receive/ship/transfer/adjust with transactions + `lockForUpdate` + owner-scoped level resolution) is the correct mutation core; `HasInventory` should forward to it, not rival it (A8).
- `CheckoutReservationService` + `InventoryReservation` + `InventoryOperation` form a coherent idempotent reservation layer (reference/owner unique, TTL, order link) — keep; needs the A9 janitor.
- Costing trio + `ValuationService` + snapshots is sound; registry adapters need naming (A10).
- `InventoryLocation` hierarchy (`parent/path/depth` + tree service) is the right shape; C2 aligns the write paths.
- `TemperatureZone` enum, `AllocationStrategy` enum vs registry strategies: reconcile names with A10b (config says `priority`, registry has fefo/nearest).

## Security Findings

- Owner scoping is defense-in-depth (model global scope + service `whereHas` + `saving()` mismatch rejection + filament scoping + console scoping). No bypass found except the intentional, commented session-style read — none here (that pattern was promotions).
- Inbound-ID validation: `InventoryLevel::saving()` validates `location_id` against owner scope (verified lines 361-380) — the reference pattern; extend the same check to movement/allocation/batch/serial `saving()` hooks if any accept raw `location_id` without it (grep before landing; filament actions must also guard).
- `DB::table` paths: none found touching tenant data without scope (reports use Eloquent + `InventoryOwnerScope`) — PASS, with A5 noting the cost.
- Mass assignment: `InventoryLevel` fillable excludes owner columns (owner set from location hook) — good; confirm other models follow the same (no `owner_type` in fillable where location-derived).

## Performance Findings

- Allocation hot paths already transactional + row-locked — good. `getOrCreateLevel` lock path needs the composite unique check (Database Findings (c)).
- A5's `orWhereHas` movement scope on large tables is the top read-cost item — addressed by scoping simplification + optional index.
- Widgets: `cache.stats_ttl` (60s default) exists — confirm all 9 widgets honor it (not just stats), and that cache keys include `InventoryOwnerScope::cacheKeySuffix()` (the helper exists precisely for this — verify usage; cross-owner cache bleed would be Critical — grep `cacheKeySuffix` callers before landing).
- Reports loop months/SKUs in PHP (`InventoryKpiService::getKpiTrends`, `StockLevelReport` ABC analysis) — fine at current scale; revisit with measured data only.

## Testing Findings

- Zero tests; 13 factories idle. Priority (Pest, `--parallel`): serial status unification (A1: filament values round-trip through the state cast); decimal-column absence post-migration (A2); reservations-migration extraction equivalence (A3: fresh-migrate schema identical); `getOrCreateLevel` concurrency (two parallel creates → one row, needs the unique index); allocation race (double-allocate same stock → one wins); owner isolation per model via `OwnerScopingContractTests`; `NearestLocationStrategy` null-coordinates-last (C3); strategy-name resolution for every configured value (A10b); `cacheKeySuffix` in widget cache keys (bleed regression).

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)

| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| `products` (`Product`/`Variant` implement `Inventoryable`, use `HasInventory`) | trait methods, availability helpers | A8 moves mutators off the trait; A2 drops columns they never touch | Migrate mutator call sites to `InventoryService`; re-run product stock tests |
| `checkout` (`ReserveInventoryStep`, `InventoryAdapter`, `EnsureCheckoutOfferProduct`, `RegisterCheckoutOptionalSteps`) | `InventoryService`, `CheckoutReservationServiceInterface`, availability queries | A5 speeds availability; A10b may change allocation order if default fixed | Confirm allocation-order expectations; adopt reservation interface (already done) |
| `orders` (deduction/release events) | `InventoryDeductionRequired/ReleaseRequired`, listeners, `InventoryOperation` rows | A9 adds cleanup around operations | None code-wise; retention policy documented |
| `shipping` (`OrderFulfillmentHandler`, `FulfillmentLocationService`) | location optimization, `NearestLocationStrategy` | C3 null-handling; A10b strategy naming | Re-verify fulfillment ranking tests |
| `cashier` (`CartIntegrationRegistrar`, `CartCheckoutBuilder`) | cart/inventory interplay | A8 if they call trait mutators | Grep + migrate to service calls |
| `cart` (events consumed by inventory listeners) | `CartCleared/Destroyed`, `ItemAdded` | None (listener side only) | None |
| `filament-inventory` | all domain models/services | A1, A2, A5, A6, A7 land as UI fixes | Per-finding changes above |
| `commerce-support` (health docs reference) | `LowStockCheck` | A9 may add checks alongside | None (additive) |

## Recommended Refactor Plan (ordered steps)

1. Unify serial status on spatie states; fix the three filament files; delete `Enums/SerialStatus.php` (A1).
2. Ship the migration release: dedicated `reservation_group_id` migration + reservations-migration cleanup (A3), decimal drops (A2), optional movement composite + levels get-or-create unique (Database (b)(c)). Pre-deploy null-check for decimals.
3. Config: add `operations` key (A4); reconcile `allocation_strategy` default with registry + name costing adapters (A10).
4. Reads: demote location-relation scope on hot paths + reconciliation coverage (A5); `parent::` queries in all six resources (A6); aggregator→reports delegation (A7).
5. Trait split `HasInventory` (A8) with `products`/`checkout`/`cashier` call-site migration; `LocationTreeService` write funnel (C2); null-coordinates-last (C3); reservations janitor + retention docs (A9); `down()` symmetry for the extracted migration.
6. Full Pest suite per Testing Findings (`./vendor/bin/pest --parallel` scoped), incl. `cacheKeySuffix` bleed test and owner-isolation contracts.

## Files Likely to Change

- `packages/filament-inventory/src/Resources/InventorySerialResource*.php` (resource/table/form), docs
- `packages/inventory/src/Enums/SerialStatus.php` (delete), `Enums/BackorderStatus.php` (verify + delete)
- `packages/inventory/database/migrations/` (1 new + 1 drop-columns + optional index/unique + reservations-file edit)
- `packages/inventory/config/inventory.php` (operations key, strategy default)
- `packages/inventory/src/Services/InventoryService.php` (scope simplification), `Services/Stock/*`, `Services/Costing/*` (named adapters), `Support/InventoryOwnerScope.php` (docs only)
- `packages/inventory/src/Traits/HasInventory.php` (split), `Traits/HasLocationHierarchy.php` (read-only)
- `packages/inventory/src/Console/*`, `src/Health/*` (janitor + checks)
- `packages/filament-inventory/src/Resources/*` (parent queries), `Services/InventoryStatsAggregator.php` (delegation), `Policies/*` (coverage confirmation)
- `packages/products/src/Models/*`, `packages/checkout/src/**/*`, `packages/shipping/src/**/*` (call-site migrations)

## Files / Code That Should Be Removed (explicit list, no legacy preservation)

- `packages/inventory/src/Enums/SerialStatus.php` — values never occur in the `status` column; all three importers switch to `States\*` (A1). (Delete only after the importer grep is re-run at implementation time.) No `Enums/BackorderStatus.php` exists — nothing to delete there.
- `quantity_on_hand_decimal` + `quantity_reserved_decimal` columns (`…000002…` migration + `InventoryLevel` fillable/casts/docblock + any filament display) — zero service readers/writers (A2).
- The `Schema::table` alteration block inside `2026_07_12_000002_create_inventory_reservations_table.php` — superseded by the dedicated migration (A3).
- `packages/inventory/src/Support/InventoryTraitsUsage.php` — pending runtime-use grep; delete if audit-only (C1).
- Anonymous costing adapters in `InventoryServiceProvider.php:195-283` — replaced by named classes (A10) (code moves, not behavior).
- `HasInventory` mutator/cart-coupled methods (list in A8) — moved to service/integration call sites, not preserved on the trait.

## Final Recommended Architecture

Keep the proven core untouched: transactional + locked `InventoryService` mutations, `CheckoutReservationService` idempotency layer, costing trio behind named adapters, `InventoryOwnerScope` as the read-scope authority with the global `OwnerScope` as enforcement. One status vocabulary per entity (spatie states where transitions matter, plain enums only where no transitions exist — never both for the same column). One quantity truth (int). One migration per schema change, no hidden alterations. Filament as a thin guarded layer: `parent::` queries, state-class values, domain-delegating actions, report-backed widgets — with `products`/`checkout`/`shipping` consuming the service interfaces, never the trait mutators.
