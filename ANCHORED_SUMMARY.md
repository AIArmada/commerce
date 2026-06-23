# ANCHORED SUMMARY — EventTicketType bundles + sub-tickets + inventory

## Goal
Add a many-to-many product bundle system (`EventTicketTypeProduct`) and full sub-ticket component support (`EventTicketTypeComponent`) to `EventTicketType`, with required auto-add to cart, optional upsells, child registration expansion, `pass_entitlements` array, inventory package adoption for tickets, and Filament admin.

## Constraints & Preferences
- No `down()`, no `constrained()`, no `cascadeOnDelete()` on migrations
- Breaking changes allowed
- PHP 8.3+, Laravel 13
- `AddEventTicketTypeToCartAction` has a `$skipQuotaValidation` param kept for BC but now checks inventory instead of quota
- Inventory check uses `class_exists(\AIArmada\Inventory\Models\InventoryLevel::class)` guard — silently skips if inventory package missing
- `AutoAddRequiredTicketBundlesAction` is gated by `CommerceIntegration::aiArmadaCheckoutAvailable()`
- Migration 000077 (quota→inventory) is safe in test envs: checks `Schema::hasTable()` before querying inventory tables
- Filament RelationManagers are wired via `EventTicketTypeResource::getRelations()` and rendered on the `ViewEventTicketType` page

## Progress
### Done
- **Config**: added `aiarmada/inventory` as hard `require` in `composer.json`; added `database.tables.event_ticket_type_products`, `bundles.sub_ticket_cart_mode`, `inventory.default_location_id`, `inventory.auto_register_quotas_on_migrate`, `integrations.inventory_location_model` to `config/events.php`
- **Enum**: `BundleInclusionMode` (Required/Optional) with `label()` and `isRequired()` helpers
- **4 migrations**: `000074_add_bundle_columns_to_event_registrations` (parent_registration_id, is_bundle_root, pass_entitlements); `000075_drop_quota_from_event_ticket_types`; `000076_create_event_ticket_type_products` (pivot with product_id/variant_id/quantity/inclusion_mode/sort_order/metadata); `000077_migrate_ticket_type_quotas_to_inventory_levels` (one-time data migration, safe in test envs)
- **Model `EventTicketTypeProduct`**: fillable, casts, boot guard (rejects both-null product_id+variant_id), `BelongsTo ticketType`, `MorphTo product`/`variant` via `CommerceIntegration::modelClass()`. Factory with `required()`/`optional()` states
- **Model `EventRegistration` updated**: `parent_registration_id`, `is_bundle_root`, `pass_entitlements` in fillable/casts; `parentRegistration()` BelongsTo, `childRegistrations()` HasMany, `getPassEntitlements()` helper
- **Model `EventTicketType` updated**: implements `InventoryableInterface`; removed `quota` from fillable/casts; added `bundleProducts()`, `requiredBundleProducts()`, `optionalBundleProducts()`, `childComponents()`, `inventoryLevels()`, `inventoryMovements()`, `inventoryAllocations()`; added `getTotalOnHand()`, `getTotalAvailable()`, `hasInventory()`, `getInventoryAtLocation()`, `getAllocationStrategy()`, `receive()`, `ship()`, `transfer()`, `allocate()`, `release()`
- **Actions (3 new)**: `AutoAddRequiredTicketBundlesAction` (scans required bundle products, resolves via `CommerceIntegration::modelClass()`, calls `$cart->add()`/`$cart->update()` with line keys `bundle_product_{id}_for_{ticket_id}` and `attributes.auto_added_for_ticket_type_id`); `ExpandTicketTypeComponentsAction` (creates child `EventRegistration` records per sub-ticket component, appends to `pass_entitlements`, sets `is_bundle_root`); `RecordAgentTicketSaleAction` (calls `InventoryService::shipFromDefault()`, creates `EventRegistration` records with `source = 'agent_sale'`, `is_bundle_root = true`, expands sub-tickets)
- **Action modifications**: `AddEventTicketTypeToCartAction` — replaces quota check with `$ticketType->hasInventory()` and calls `AutoAddRequiredTicketBundlesAction::handle($cart, $ticketType, $quantity)` after cart add; `CreateRegistrationsFromOrderAction` — calls `ExpandTicketTypeComponentsAction::handle()` for each created registration
- **Cancellation cascade listener**: `CancelBundleChildrenOnParentCanceled` listens to `EventRegistrationCancelled`, cancels all child registrations of a cancelled `is_bundle_root` registration via `OwnerContext::withOwner()`
- **Service Provider**: registered `AutoAddRequiredTicketBundlesAction`, `ExpandTicketTypeComponentsAction`, `RecordAgentTicketSaleAction` as singletons; registered `EventRegistrationCancelled` → `CancelBundleChildrenOnParentCanceled` event listener
- **Filament**: `EventTicketTypeResource` — replaced `quota` column with `getTotalOnHand`/`getTotalAvailable`; added `getRelations()` returning `EventTicketTypeBundleProductsRelationManager` and `EventTicketTypeComponentsRelationManager`; both RelationManagers created at `Resources/EventTicketTypeResource/RelationManagers/`
- **Pint**: 20 style issues fixed across 583 files
- **PHPStan level 6**: 0 errors on new/changed files
- **Pre-existing Spatie v2.14.1 upgrade fixes**:
  - `HasStates` trait moved from `Spatie\ModelStates\Traits\HasStates` to `Spatie\ModelStates\HasStates` — fixed imports in 5 models (Event, EventOccurrence, EventSession, EventRegistration, EventSubmission)
  - `getMorphClass()` changed from using `name()` method to `static::$name` property — added `protected static string $name` to all 38 state subclasses. Without this, `resolveStateMapping()` used FQCNs as keys but lookups used the short name string, causing `UnknownState` errors.

### Remaining pre-existing failures
25 test failures remain, all pre-existing from the Spatie v2.14.1 upgrade (confirmed by `git stash` test run):
- **TransitionNotFound**: Several actions define `->allowTransition()` in `config()` — v2.14.1 may require explicit transition class registration. Affects: EventCreationTest, EventLifecycleWorkflowTest, EventNotificationsTest, EventSessionActionsTest, RegistrationServiceTest, EventOccurrenceActionsTest
- **PromoteInterestedToConfirmedActionTest**: 6 failures — mock/PHP 8.4 compatibility issue with `EventPassIssuer`

### In Progress
- (none — all implementation done)

### Blocked
- Pre-existing Spatie v2.14.1 transition registration issue (not in scope)

## Key Decisions
- **Polymorphic morphTo for product/variant**: `EventTicketTypeProduct.product()` and `variant()` use `MorphTo` with `product_type`/`variant_type` columns (string type hints) to avoid hard coupling to the products package FQCN. When the products package is absent, the relationship resolves an empty `MorphTo` with no results (never crashes).
- **Cart line keys**: `bundle_product_{id}_for_{ticket_id}` or `bundle_variant_{id}_for_{ticket_id}` — distinct from the ticket's own line key (`$ticketType->getKey()`). Uses `$cart->has()`/`$cart->get()`/`$cart->update()` with `['quantity' => ['value' => $newQuantity]]` for absolute updates.
- **Child registration expansion**: happens inside `CreateRegistrationsFromOrderAction` (called by the step) and `RecordAgentTicketSaleAction`. Not in the step itself. `ExpandTicketTypeComponentsAction` handles the actual expansion.
- **Inventory migration guard**: migration 000077 checks `Schema::hasTable('inventory_levels')` and `Schema::hasTable('inventory_locations')` — silently skips when tables don't exist (e.g. in-memory SQLite test envs).
- **Cancellation cascade**: `CancelBundleChildrenOnParentCanceled` cancels children via `RegistrationServiceInterface::cancel()` (which fires `EventRegistrationCancelled` again — but children have `is_bundle_root = false`, so the listener skips them, avoiding infinite recursion).
- **Spatie `$name` property fix**: v2.14.1 changed `getMorphClass()` to use `static::$name` (property) instead of `name()` method. All 38 state subclasses need `protected static string $name = 'xxx'` matching the `name()` return value.
- **Addressing integration (Option B, exclusive storage)**: Config flag `events.integrations.addressing_enabled` (default `false`). When off, Venue/EventLocation use flat columns. When on, they use `addresses()` morphToMany via `addressables` pivot to the `Address` model. `AddressData` DTO is the uniform interface via `getPrimaryAddressData()`. Storage is exclusive per config — no dual-write. Addressing package is not hard-required; `class_exists(Address::class)` guards all paths.

## Next Steps
1. **Fix pre-existing Spatie v2.14.1 transition registration**: `config()` in each base state class may need explicit transition class registration (or check if `registerStatesFromDirectory` or `registerState` handles this)
2. **Write new tests**: unit (`BundleInclusionMode`, `EventTicketTypeProduct` model boot guard, factory states); integration (`AddEventTicketTypeToCartAction` auto-adds required products, stock check via inventory, `ExpandTicketTypeComponentsAction`, `RecordAgentTicketSaleAction`, cancellation cascade); Filament (RelationManager renders columns)
3. **Update documentation**: `packages/events/docs/01-overview.md` (core concepts), `03-configuration.md` (new config keys), `04-usage.md` (bundles, sub-tickets, agent sales, stock), `packages/filament-events/docs/04-usage.md` (RelationManagers)
4. **PHPStan level 6 on whole events package** — ensure 0 errors after pre-existing trait fixes
5. **Verify**: Pint, full integration test suite, manual Filament smoke-test

## Test Results (after fixes)
- Records with pre-existing failures: `106 passed`, `25 failed`
- Without pre-existing failures: `106 passed`, `0 failed`
- `RecordWalkInActionTest`: 4/4 passing (was 0/4 before `$name` fix)

## Relevant Files
- `packages/events/src/Enums/BundleInclusionMode.php`: new enum
- `packages/events/src/Models/EventTicketTypeProduct.php`: new model
- `packages/events/src/Models/EventTicketType.php`: updated — InventoryableInterface, bundle relations, stock methods
- `packages/events/src/Models/EventRegistration.php`: updated — bundle columns, parent/child relations
- `packages/events/src/Actions/AutoAddRequiredTicketBundlesAction.php`: new
- `packages/events/src/Actions/ExpandTicketTypeComponentsAction.php`: new
- `packages/events/src/Actions/RecordAgentTicketSaleAction.php`: new
- `packages/events/src/Actions/AddEventTicketTypeToCartAction.php`: modified — inventory check + auto-add call
- `packages/events/src/Actions/CreateRegistrationsFromOrderAction.php`: modified — expand sub-tickets
- `packages/events/src/Listeners/CancelBundleChildrenOnParentCanceled.php`: new
- `packages/events/src/EventsServiceProvider.php`: updated — singletons + event listener
- `packages/events/config/events.php`: updated — new keys
- `packages/events/composer.json`: updated — inventory hard require
- `packages/events/database/migrations/000074*` to `000077*`: 4 new migrations
- `packages/events/database/factories/EventTicketTypeProductFactory.php`: new factory
- `packages/filament-events/src/Resources/EventTicketTypeResource.php`: updated — quota→stock, RelationManagers
- `packages/filament-events/src/Resources/EventTicketTypeResource/RelationManagers/EventTicketTypeBundleProductsRelationManager.php`: new
- `packages/filament-events/src/Resources/EventTicketTypeResource/RelationManagers/EventTicketTypeComponentsRelationManager.php`: new
- **38 state subclasses** across `EventStatus/`, `OccurrenceStatus/`, `RegistrationStatus/`, `EventModerationStatus/`: added `protected static string $name` property
- **5 models**: fixed `HasStates` trait import path
- **4 state configs**: fixed `registerStatesFromDirectory` → explicit `registerState()`
- **Addressing package upgrades**:
  - `Addressable` model: added `scopeValidNow()` for correctly-filtered valid-from/valid-until queries
  - `HasAddresses` trait: `primaryAddress()` and `addressesOfType()` now check `relationLoaded('addresses')` first, avoiding N+1 on eager-loaded collections
  - `HasAddresses` trait: added `scopeWithPrimaryAddress()` for standard eager-load pattern
  - Composite indexes: added `(country_code, city)` and `(country_code, postcode)` on `addresses`, `(addressable_type, addressable_id, is_primary)` on `addressables` — new migration `2001_01_01_000006`
- **Addressing integration into events package (Option B, config-driven exclusive storage)**:
  - Config key: `events.integrations.addressing_enabled` (default `false`, via `EVENTS_ADDRESSING_ENABLED`)
  - New trait `Addressable` in `packages/events/src/Models/Concerns/Addressable.php` — when enabled, provides full `addresses()` morphToMany to `Address` via `addressables` pivot; when disabled, falls back to flat columns
  - `getPrimaryAddressData(): ?AddressData` works with both backends — returns `AddressData` DTO uniformly
  - Applied to `Venue` and `EventLocation` models
  - No data sync issues: storage is exclusive per config flag, not dual-written
  - No addressing package hard-require needed: `class_exists(Address::class)` guards all paths

## Relevant Files (addressing integration)
- `packages/addressing/src/Models/Addressable.php`: added `scopeValidNow()`
- `packages/addressing/src/Traits/HasAddresses.php`: N+1 fix + `scopeWithPrimaryAddress()`
- `packages/addressing/database/migrations/2001_01_01_000006_*`: composite indexes
- `packages/events/src/Models/Concerns/Addressable.php`: new trait (config-driven addressing)
- `packages/events/src/Models/Venue.php`: added `use Addressable`
- `packages/events/src/Models/EventLocation.php`: added `use Addressable`
- `packages/events/config/events.php`: added `integrations.addressing_enabled`
