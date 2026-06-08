# Products friendliness review

This note reviews `packages/products` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Models`
- `src/Events`
- `src/Contracts`
- `src/Enums`
- `src/Policies`
- `src/Traits`
- `src/ProductsServiceProvider.php`
- downstream consumers in `cart`, `checkout`, `pricing`, `inventory`, `vouchers`, `events`

## What is already friendly

### Real buyable contract

- `Contracts/Buyable.php`
- `Contracts/Priceable.php`
- `Contracts/Inventoryable.php`

This is the right shape. Pricing, cart, and inventory all depend on these contracts rather than the concrete `Product` model. New buyable types (subscriptions, gift cards, services) can implement the same contract without inheriting from `Product`.

### Domain events are explicit

- `Events/ProductCreated.php`
- `Events/ProductUpdated.php`
- `Events/ProductDeleted.php`
- `Events/ProductStatusChanged.php`
- `Events/VariantsGenerated.php`

These give downstream packages (cart, signals, search) a stable surface to react to catalog changes.

### Policies cover the full model surface

- `Policies/ProductPolicy.php`
- `Policies/CategoryPolicy.php`
- `Policies/CollectionPolicy.php`
- `Policies/Attribute*Policy.php`

Authorization is uniformly modeled per model.

## Findings

### 1. There is no `Services/` or `Actions/` directory — the package is model-only

**Files**

- `src/Models/Product.php`
- `src/Models/Variant.php`
- `src/Models/Attribute*.php`
- `src/Models/Option*.php`

**Why this hurts friendliness**

Every product-related orchestration (variant generation, status changes, bulk imports) is currently inline in the model, in a controller, in a Filament resource, or in a downstream package. As the package grows, the orchestration will fragment.

**Recommendation**

Start with a thin `src/Actions` tree for the most common operations:

- `Actions/CreateProduct`
- `Actions/UpdateProductStatus`
- `Actions/GenerateVariants`
- `Actions/ApplyAttributeChanges`

Models become state + relations, Actions become the orchestration surface. This matches the monorepo's "Actions only, no logic in models" rule.

### 2. Variant generation is likely a hard-coded method

**Files**

- `src/Models/Variant.php`
- `src/Models/Product.php`
- the `Events/VariantsGenerated` event

**Why this hurts friendliness**

The package has a `VariantsGenerated` event, but the logic that produces it is not clearly an Action. New variant generation strategies (size matrices, bundle splits, subscription variants) will copy the existing implementation.

**Recommendation**

Move variant generation behind a `VariantGenerator` contract or strategy. Each strategy is a class, the orchestrator picks the right one based on product type, and the event is dispatched from the orchestrator.

### 3. Status change logic is split between events and possibly inline code

**Files**

- `Events/ProductStatusChanged.php`
- `Enums/ProductStatus.php`
- `Enums/ProductVisibility.php`

**Why this hurts friendliness**

The event is declared but its producer and any status transition policy are not clearly owned. Listeners across packages need to be confident the event fires consistently.

**Recommendation**

Add an `Actions/UpdateProductStatus` that owns the state transition and dispatches `ProductStatusChanged`. Use it from all status-change call sites.

### 4. Attribute and option model clusters have similar boilerplate

**Files**

- `src/Models/Attribute.php`
- `src/Models/AttributeGroup.php`
- `src/Models/AttributeSet.php`
- `src/Models/AttributeValue.php`
- `src/Models/Option.php`
- `src/Models/OptionValue.php`

**Why this hurts friendliness**

These are sibling models with similar structure (group/value, group/value). Each is hand-rolled with its own factory, casts, and relations.

**Recommendation**

Consider shared concerns for `IsAttributeEntity` and `IsOptionEntity`. Pull out common scopes, casts, and event dispatches.

### 5. The package has no `Console/` directory

**Why this hurts friendliness**

Bulk operations (variant regeneration, attribute rebuild, status migration) currently have no clear owner. As the catalog grows, the need for these operations will too.

**Recommendation**

Add a `src/Console/Commands` directory when the first batch operation is needed. Wire it through a `Console` registrar following the monorepo's pattern.

### 6. The provider is small today, which is good

**Files**

- `src/ProductsServiceProvider.php`

**Why this is worth noting**

The provider is currently lean. This is the right starting state. Keep the provider as a composition root and avoid turning it into a wiring hub.

## Concrete refactor plan

### Phase 1 — introduce the Actions tree

**Steps**

1. Add `src/Actions/CreateProduct`, `UpdateProductStatus`, `GenerateVariants`, `ApplyAttributeChanges`.
2. Move any inline orchestration out of models.
3. Update downstream callers to use Actions.

### Phase 2 — extract variant generation strategy

**Steps**

1. Add `Contracts/VariantGeneratorInterface`.
2. Build the first concrete generator (matrix-based).
3. Register it from the service provider.
4. Dispatch `VariantsGenerated` from the generator, not the model.

### Phase 3 — share attribute/option concerns

**Steps**

1. Add `Concerns/IsAttributeEntity` and `Concerns/IsOptionEntity`.
2. Apply to the sibling models.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — introduce the Actions tree

- [pending] Add `src/Actions/CreateProduct`, `UpdateProductStatus`, `GenerateVariants`, `ApplyAttributeChanges`.
- [pending] Move any inline orchestration out of models.
- [pending] Update downstream callers to use Actions.

### Phase 2 — extract variant generation strategy

- [pending] Add `Contracts/VariantGeneratorInterface`.
- [pending] Build the first concrete generator (matrix-based).
- [pending] Register it from the service provider.
- [pending] Dispatch `VariantsGenerated` from the generator, not the model.

### Phase 3 — share attribute/option concerns

- [pending] Add `Concerns/IsAttributeEntity` and `Concerns/IsOptionEntity`.
- [pending] Apply to the sibling models.



## Suggested verification scope

- `tests/src/Products/Unit/ProductTest.php`
- `tests/src/Products/Unit/VariantTest.php`
- new tests for the Actions introduced in Phase 1
- variant generation tests after the strategy extraction

## Recommended first move

Phase 1 — introduce the Actions tree. The package currently has no orchestration surface, and the most common product operations (create, status change, variant generation) all want Actions today.
