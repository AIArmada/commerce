## Plan: Digital Tickets With Stock Tracking

Refactor `commerce` so `Digital` products can optionally support variants and inventory without collapsing the existing meaning of `Configurable`. Then adopt that capability model in `unfair` so AI Awakening remains digital / non-shipping while each occurrence gets its own variant and stock bucket.

### Tracking summary

- [x] Phase 1 — split product type from capabilities
- [x] Phase 2 — update products package model contract
- [x] Phase 3 — persist capability fields and defaults
- [x] Phase 4 — update factories and core tests
- [x] Phase 5 — update Filament product management
- [x] Phase 6 — update checkout offer bootstrap
- [x] Phase 7 — audit inventory listeners and capability-gated flows
- [x] Phase 8 — document the new digital semantics
- [x] Phase 9 — adopt the capability model in `unfair`
- [x] Phase 10 — make `unfair` checkout variant-first
- [x] Phase 11 — enforce capacity + stock availability in `unfair`
- [x] Phase 12 — complete regression verification

### Phase 1 — split product type from capabilities

- [x] Refactor `/Users/Saiffil/Herd/commerce/packages/products/src/Enums/ProductType.php` so `Digital` no longer hard-codes “no variants” as a type invariant.
- [x] Introduce explicit product capabilities on the `Product` model, recommended as persisted booleans or equivalent explicit state:
  - [x] `supports_variants`
  - [x] `tracks_inventory`
- [x] Keep `requires_shipping` as the delivery / shipping concern.
- [x] Preserve `Configurable` as the existing type for physical configurable goods.
- [x] Allow `Digital` products to opt into the same capabilities when needed.

### Phase 2 — update products package model contract

- [x] Update `/Users/Saiffil/Herd/commerce/packages/products/src/Models/Product.php` so:
  - [x] `hasVariants()` uses the explicit capability, not only `type->hasVariants()`.
  - [x] `tracksInventory()` uses the explicit capability, not `! $this->isDigital()`.
  - [x] `isDigital()` continues to mean non-shipping / digital fulfillment style, not “never inventory-tracked.”
- [x] Review `/Users/Saiffil/Herd/commerce/packages/products/src/Models/Variant.php` and add an explicit `tracksInventory()` helper if that keeps inventory behavior honest when it inspects a purchasable model.
- [x] Confirm `/Users/Saiffil/Herd/commerce/packages/products/src/Contracts/Inventoryable.php` still fits the new capability model.

### Phase 3 — persist capability fields and defaults

- [x] Update the products package schema so the new capabilities are persisted explicitly.
- [x] Update model defaults so new records behave cleanly:
  - [x] Digital defaults to non-variant / non-inventory unless explicitly enabled.
  - [x] Configurable continues to default to variant-capable.
- [x] Keep this clean-cut:
  - [x] no backfill
  - [x] no backward-compatibility shim
  - [x] no legacy support path

### Phase 4 — update factories and core tests

- [x] Update `/Users/Saiffil/Herd/commerce/packages/products/database/factories/ProductFactory.php` so it can create:
  - [x] unlimited digital
  - [x] inventoryable digital
  - [x] variant-capable digital
- [x] Update `/Users/Saiffil/Herd/commerce/tests/src/Products/Enums/ProductTypeTest.php`.
- [x] Update `/Users/Saiffil/Herd/commerce/tests/src/Products/ProductModelTest.php`.
- [x] Add coverage for:
  - [x] digital unlimited
  - [x] digital inventory-tracked
  - [x] digital with variants
  - [x] configurable still working as before

### Phase 5 — update Filament product management

- [x] Update `/Users/Saiffil/Herd/commerce/packages/filament-products/src/Resources/ProductResource.php` so the admin UI exposes the new capabilities explicitly.
- [x] Remove wording or assumptions that only configurable products can manage variants.
- [x] Update `/Users/Saiffil/Herd/commerce/packages/filament-products/src/Resources/ProductResource/RelationManagers/VariantsRelationManager.php` tests and any resource logic that assumes only configurable products use that relation manager.
- [x] Update `/Users/Saiffil/Herd/commerce/packages/filament-products/docs/04-usage.md` and related docs so they describe variant management in capability terms rather than type-only terms.

### Phase 6 — update checkout offer bootstrap

- [x] Keep `/Users/Saiffil/Herd/commerce/packages/checkout/src/Data/CheckoutOfferProductData.php` defaulting checkout offers to `Digital`.
- [x] Extend the DTO or bootstrap path so checkout-created digital offers can opt into:
  - [x] `supports_variants`
  - [x] `tracks_inventory`
- [x] Update `/Users/Saiffil/Herd/commerce/packages/checkout/src/Actions/EnsureCheckoutOfferProduct.php` to persist those capabilities.

### Phase 7 — audit inventory listeners and capability-gated flows

- [x] Audit `/Users/Saiffil/Herd/commerce/packages/inventory/src/Listeners/DeductInventoryFromOrder.php`.
- [x] Audit `/Users/Saiffil/Herd/commerce/packages/inventory/src/Integrations/FulfillmentLocationService.php`.
- [x] Audit capability-gated stock helpers in `/Users/Saiffil/Herd/commerce/packages/products/src/Models/Product.php`.
- [x] Confirm inventoryable digital products behave like stock-managed products.
- [x] Confirm unlimited digital products still bypass stock checks.

### Phase 8 — document the new digital semantics

- [x] Keep `Configurable` documented as the type for physical configurable goods.
- [x] Define the new supported case explicitly: `Digital` product with variants and optional inventory.
- [x] Document the model split:
  - [x] `ProductType` = fulfillment / category semantics
  - [x] capabilities = behavior semantics
- [x] Update package docs to reflect the orthogonal model.

### Phase 9 — adopt the capability model in unfair

- [x] Update `/Users/Saiffil/Herd/unfair/config/event-offer.php` so the AI Awakening parent product is still digital / non-shipping but explicitly variant-capable and inventory-tracked.
- [x] Refactor `/Users/Saiffil/Herd/unfair/app/Actions/Checkout/EnsureEventOfferProductAction.php` so it creates:
  - [x] one digital parent product
  - [x] one variant per occurrence
  - [x] one inventory row per variant
- [x] Keep the public date-picker UX.
- [x] Map selected date → variant internally.

### Phase 10 — make unfair checkout variant-first

- [x] Update `/Users/Saiffil/Herd/unfair/app/Actions/Checkout/EnsureEventOccurrenceAction.php` to populate `variant_id`.
- [x] Update `/Users/Saiffil/Herd/unfair/app/Actions/Checkout/PrepareCheckoutSessionAction.php` so the selected variant becomes the real purchasable.
- [x] Update `/Users/Saiffil/Herd/commerce/packages/checkout/src/Steps/CreateOrderStep.php` so explicit `purchasable_id` / `purchasable_type` wins over plain `product_id`.
- [x] Update `/Users/Saiffil/Herd/unfair/app/Support/EventOfferOrderItemFulfillmentResolver.php` so fulfillment resolves by `variant_id` only.
- [x] Treat `preferred_date` as UX metadata, not as an authority or fallback.

### Phase 11 — enforce capacity + stock availability in unfair

- [x] Update `/Users/Saiffil/Herd/unfair/app/Actions/Checkout/ResolveOccurrenceAvailabilityAction.php`.
- [x] Update `/Users/Saiffil/Herd/unfair/app/Http/Requests/StartCheckoutRequest.php`.
- [x] Make effective availability the minimum of:
  - [x] occurrence capacity remaining
  - [x] selected variant stock available

### Phase 12 — complete regression verification

- [x] Update `commerce` tests:
  - [x] `/Users/Saiffil/Herd/commerce/tests/src/Products/Enums/ProductTypeTest.php`
  - [x] `/Users/Saiffil/Herd/commerce/tests/src/Products/ProductModelTest.php`
  - [x] `/Users/Saiffil/Herd/commerce/tests/src/FilamentProducts/Integration/RelationManagersTest.php`
  - [x] `/Users/Saiffil/Herd/commerce/tests/src/Checkout/EnsureCheckoutOfferProductTest.php`
  - [x] `/Users/Saiffil/Herd/commerce/tests/src/Checkout/PaymentFlowTest.php`
  - [x] targeted inventory tests touching `tracksInventory()` behavior
- [x] Update `unfair` tests:
  - [x] `/Users/Saiffil/Herd/unfair/tests/Feature/EventOfferSeederTest.php`
  - [x] `/Users/Saiffil/Herd/unfair/tests/Feature/CheckoutAvailabilityTest.php`
  - [x] `/Users/Saiffil/Herd/unfair/tests/Feature/CheckoutFlowTest.php`
  - [x] `/Users/Saiffil/Herd/unfair/tests/Feature/CheckoutFulfillmentTest.php`
- [x] Add assertions that the system works only through the new variant-authoritative path.

### Key files

- `/Users/Saiffil/Herd/commerce/packages/products/src/Enums/ProductType.php`
- `/Users/Saiffil/Herd/commerce/packages/products/src/Models/Product.php`
- `/Users/Saiffil/Herd/commerce/packages/products/src/Models/Variant.php`
- `/Users/Saiffil/Herd/commerce/packages/products/src/Contracts/Inventoryable.php`
- `/Users/Saiffil/Herd/commerce/packages/products/database/factories/ProductFactory.php`
- `/Users/Saiffil/Herd/commerce/packages/checkout/src/Data/CheckoutOfferProductData.php`
- `/Users/Saiffil/Herd/commerce/packages/checkout/src/Actions/EnsureCheckoutOfferProduct.php`
- `/Users/Saiffil/Herd/commerce/packages/checkout/src/Steps/CreateOrderStep.php`
- `/Users/Saiffil/Herd/commerce/packages/inventory/src/Listeners/DeductInventoryFromOrder.php`
- `/Users/Saiffil/Herd/commerce/packages/inventory/src/Integrations/FulfillmentLocationService.php`
- `/Users/Saiffil/Herd/commerce/packages/filament-products/src/Resources/ProductResource.php`
- `/Users/Saiffil/Herd/commerce/packages/filament-products/src/Resources/ProductResource/RelationManagers/VariantsRelationManager.php`
- `/Users/Saiffil/Herd/commerce/packages/filament-products/docs/04-usage.md`
- `/Users/Saiffil/Herd/unfair/config/event-offer.php`
- `/Users/Saiffil/Herd/unfair/app/Actions/Checkout/EnsureEventOfferProductAction.php`
- `/Users/Saiffil/Herd/unfair/app/Actions/Checkout/EnsureEventOccurrenceAction.php`
- `/Users/Saiffil/Herd/unfair/app/Actions/Checkout/PrepareCheckoutSessionAction.php`
- `/Users/Saiffil/Herd/unfair/app/Actions/Checkout/ResolveOccurrenceAvailabilityAction.php`
- `/Users/Saiffil/Herd/unfair/app/Http/Requests/StartCheckoutRequest.php`
- `/Users/Saiffil/Herd/unfair/app/Support/EventOfferOrderItemFulfillmentResolver.php`

### Verification commands

- [x] In `commerce`, run:
  - [x] `./vendor/bin/pest --parallel tests/src/Products/Enums/ProductTypeTest.php tests/src/Products/ProductModelTest.php tests/src/FilamentProducts/Integration/RelationManagersTest.php tests/src/Checkout/EnsureCheckoutOfferProductTest.php tests/src/Checkout/PaymentFlowTest.php`
- [x] In `commerce`, run targeted inventory tests that exercise `tracksInventory()`-gated behavior.
- [x] In `unfair`, run:
  - [x] `vendor/bin/pest --parallel tests/Feature/EventOfferSeederTest.php tests/Feature/CheckoutAvailabilityTest.php tests/Feature/CheckoutFlowTest.php tests/Feature/CheckoutFulfillmentTest.php`
- [x] Manual admin verification:
  - [x] a Digital product can opt into variants without becoming Configurable
  - [x] a Digital product can opt into inventory tracking without requiring shipping
  - [x] unlimited Digital products remain unlimited by default
  - [x] event-ticket Digital products can have two variants with separate stock rows
- [x] Manual checkout verification in `unfair`:
  - [x] selected date resolves to the correct variant
  - [x] only that variant stock changes on payment
  - [x] occurrence capacity and variant stock both gate availability
  - [x] registrations are created only for the selected occurrence
  - [x] no path depends on legacy fallback logic

### Decisions

- `Digital` products gain **optional** variant and inventory capabilities.
- Not all Digital products become stock-managed or variant-capable by default.
- `Configurable` remains a separate type for physical configurable products.
- `ProductType` describes fulfillment/category semantics.
- Capabilities describe behavior.
- `unfair` is the first adopter of the new Digital-ticket capabilities.
- No backfill, no backward-compatibility shim, and no legacy fallback are in scope.

### Tracking notes

- Treat `variant_id` as the only inventory and fulfillment authority once the refactor lands.
- Treat `preferred_date` as optional UX metadata only.
- Prefer phase completion by proof: update the checkbox only after code, tests, and manual verification for that phase are done.
