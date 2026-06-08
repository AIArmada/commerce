# Affiliate Network friendliness review

This note reviews `packages/affiliate-network` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Services` (3 classes)
- `src/Http/Controllers` (1 controller)
- `src/Http/Middleware` (1 middleware)
- `src/Listeners` (1 listener)
- `src/Models` (6 classes)
- `routes/api.php`
- downstream consumers in `affiliates`, `checkout`, `orders`

## What is already friendly

### Service split is clear

- `Services/OfferLinkService.php`
- `Services/OfferManagementService.php`
- `Services/SiteVerificationService.php`

The three services each own a distinct concern. This is a clean split.

### Models are organized

- 6 models: `AffiliateOffer`, `AffiliateOfferApplication`, `AffiliateOfferCategory`, `AffiliateOfferCreative`, `AffiliateOfferLink`, `AffiliateSite`

The model surface is clear.

### Route is signed and minimal

- `routes/api.php` — `GET /affiliate-network/go/{code}` (signed, invokable controller)

The single route is well-scoped.

### Listener reacts to order events

- `Listeners/RecordNetworkConversionForOrder.php`

The package reacts to `OrderPaid`/`OrderRefunded` through a listener, not by reaching into orders.

## Findings

### 1. There is no `Actions/` directory

**Why this hurts friendliness**

The package has services but no Actions. All mutations (create offer, apply offer, record conversion) likely live inline in services. The monorepo rule is "Actions only."

**Recommendation**

Introduce a small `src/Actions` tree:

- `Actions/CreateOffer`
- `Actions/UpdateOffer`
- `Actions/ApplyToOffer`
- `Actions/ApproveApplication`
- `Actions/RecordNetworkConversion`

The three services become either thin orchestrators or read-side (queries, lookups, verification).

### 2. `SiteVerificationService` is the right shape but the seam is unclear

**Files**

- `src/Services/SiteVerificationService.php`

**Why this hurts friendliness**

Site verification (proving the affiliate owns the site) is a variant-heavy area. Different verification methods (DNS, HTML file, meta tag) will need their own implementations. The current shape may be a single class with branching.

**Recommendation**

Extract a `SiteVerificationStrategyInterface` and one implementation per method. The service coordinates the strategies.

### 3. `OfferLinkService` and `OfferManagementService` overlap on the offer model

**Files**

- `src/Services/OfferLinkService.php`
- `src/Services/OfferManagementService.php`

**Why this hurts friendliness**

The two services likely both touch the `AffiliateOffer` and `AffiliateOfferLink` models. The split between "link" and "management" is unclear.

**Recommendation**

Audit both. The link service should focus on URL signing, redirect handling, and click attribution. The management service should focus on offer lifecycle. Make the boundary explicit.

### 4. Listener is a single class — this is the right shape

**Files**

- `src/Listeners/RecordNetworkConversionForOrder.php`

**Why this is worth noting**

A focused listener that reacts to order events. Keep this discipline.

### 5. Middleware is package-local

**Files**

- `src/Http/Middleware/TrackNetworkLinkCookie.php`

**Why this hurts friendliness**

The middleware tracks link clicks via cookies. If the same cookie-tracking pattern is needed elsewhere (affiliates, signals), it may be duplicated.

**Recommendation**

Audit `TrackNetworkLinkCookie` against the affiliates' cookie attribution. If they overlap, extract a shared cookie helper to `commerce-support`.

### 6. The `AffiliateNetworkServiceProvider` is small

**Files**

- `src/AffiliateNetworkServiceProvider.php`

**Why this is worth noting**

The provider is currently lean. Use the monorepo's tagged-registrar pattern as strategies multiply.

### 7. No `Events/` directory

**Why this hurts friendliness**

The package consumes order events but may not produce its own. Downstream packages (affiliates, signals, dashboard) may need to react to offer lifecycle.

**Recommendation**

Add events:

- `Events/OfferCreated`
- `Events/OfferUpdated`
- `Events/ApplicationSubmitted`
- `Events/ApplicationApproved`
- `Events/NetworkConversionRecorded`

### 8. No `Console/Commands` directory

**Why this hurts friendliness**

Bulk operations (offer archival, application review queues, conversion reconciliation) have no clear owner.

**Recommendation**

Add a `src/Console/Commands` directory when the first batch operation is needed.

## Concrete refactor plan

### Phase 1 — introduce the Actions tree

**Steps**

1. Add `src/Actions/CreateOffer`, `UpdateOffer`, `ApplyToOffer`, `ApproveApplication`, `RecordNetworkConversion`.
2. Move orchestration out of services.
3. Add tests for each Action.

### Phase 2 — extract site verification strategy

**Steps**

1. Add `Contracts/SiteVerificationStrategyInterface`.
2. Add DNS, HTML file, meta tag implementations.
3. Make `SiteVerificationService` a coordinator.

### Phase 3 — audit offer link vs management services

**Steps**

1. Audit `OfferLinkService` and `OfferManagementService`.
2. Make the boundary explicit.
3. Document the split.

### Phase 4 — add domain events

**Steps**

1. Add the missing events.
2. Dispatch from the new Actions.
3. Update signals/affiliates listeners.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — introduce the Actions tree

- [pending] Add `src/Actions/CreateOffer`, `UpdateOffer`, `ApplyToOffer`, `ApproveApplication`, `RecordNetworkConversion`.
- [pending] Move orchestration out of services.
- [pending] Add tests for each Action.

### Phase 2 — extract site verification strategy

- [pending] Add `Contracts/SiteVerificationStrategyInterface`.
- [pending] Add DNS, HTML file, meta tag implementations.
- [pending] Make `SiteVerificationService` a coordinator.

### Phase 3 — audit offer link vs management services

- [pending] Audit `OfferLinkService` and `OfferManagementService`.
- [pending] Make the boundary explicit.
- [pending] Document the split.

### Phase 4 — add domain events

- [pending] Add the missing events.
- [pending] Dispatch from the new Actions.
- [pending] Update signals/affiliates listeners.



## Suggested verification scope

- per-Action tests
- service tests
- controller and middleware tests
- cross-package tests for affiliates/checkout/orders

## Recommended first move

Phase 1 — introduce the Actions tree. The package has services but no Actions, and the monorepo rule is consistent. The split is mostly mechanical and unblocks the strategy and boundary audits.
