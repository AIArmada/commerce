# Filament Affiliates friendliness review

This note reviews `packages/filament-affiliates` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (13 — largest in the monorepo)
- `src/Pages` (11, including 8 in `Pages/Portal/`)
- `src/Widgets` (6)
- `src/Actions`
- `src/Services` (2)
- `src/Policies` (3)
- `src/Concerns`
- `src/Support` (including ad-hoc `OwnerScopedQuery`)
- `AffiliatePanelProvider.php` (panel separation)
- `FilamentAffiliatesPlugin.php`
- downstream in `affiliates`, `affiliate-network`, `checkout`, `signals`

## What is already friendly

### Plugin gates admin features

- `FilamentAffiliatesPlugin.php` reads `config('filament-affiliates.features.admin')` to gate resources, pages, and widgets.

This is the right pattern for admin-only gating.

### Panel provider separates portal from admin

- `AffiliatePanelProvider.php`

The portal/admin split is real. Customers see the portal, admins see the admin panel.

### Policies for some resources

- `Policies/AffiliateConversionPolicy.php`
- `Policies/AffiliateFraudSignalPolicy.php`
- `Policies/AffiliatePayoutPolicy.php`

Authorization is contracted for the most sensitive resources.

## Findings

### 1. `Pages/Portal/` is 8 hand-rolled pages duplicating Resource UI

**Files**

- `Pages/Portal/PortalDashboard.php`
- `Pages/Portal/PortalConversions.php`
- `Pages/Portal/PortalLinks.php`
- `Pages/Portal/PortalPayouts.php`
- `Pages/Portal/PortalProfile.php`
- `Pages/Portal/PortalPrograms.php`
- `Pages/Portal/PortalRegistration.php`
- `Pages/Portal/PortalSupport.php`

**Why this hurts friendliness**

The portal exposes 8 separate pages that mostly mirror the admin Resources. Each is a hand-rolled Filament page. As resources grow, the portal pages will drift.

**Recommendation**

Consider a generic `PortalPage` that takes a resource, a view mode, and a scope. The 8 pages become thin adapters. Or, keep them but extract a `Concerns/InteractsWithPortalSession` shared trait.

### 2. Plugin only registers admin surfaces — portal pages are not registered here

**Files**

- `FilamentAffiliatesPlugin.php`
- `Pages/Portal/Portal*.php`

**Why this hurts friendliness**

The plugin's `getPages()` returns admin pages only. The portal pages are wired through the panel provider. The relationship is unclear.

**Recommendation**

Document the wiring. Either:

- move portal page registration into the plugin (with `getPortalPages()`), or
- document that the panel provider owns portal wiring

### 3. `Support/OwnerScopedQuery.php` is an ad-hoc owner-scope helper

**Files**

- `src/Support/OwnerScopedQuery.php`

**Why this hurts friendliness**

`commerce-support` provides `OwnerQuery`, `OwnerWriteGuard`, and `OwnerScope`. The local helper duplicates the pattern.

**Recommendation**

Replace with `commerce-support`'s primitives. Delete the local helper.

### 4. Cross-package bridges live in the Filament package

**Files**

- `src/Support/Integrations/CartBridge.php`
- `src/Support/Integrations/VoucherBridge.php`

**Why this hurts friendliness**

A Filament package should not own cart or voucher behavior. Bridges belong in the domain packages (`affiliates` already has `CartIntegrationRegistrar` and `VoucherIntegrationRegistrar`).

**Recommendation**

Move bridges to the `affiliates` domain package. The Filament package consumes them.

### 5. Resource RMs are heavy

**Files**

- `AffiliateResource` has 6 RelationManagers (Programs, Links, Conversions, Payouts, PayoutMethods, PayoutHolds, Vouchers)
- `AffiliateProgramResource` has 5 RelationManagers (Tiers, Memberships, CommissionPromotions, CommissionRules, Creatives)

**Why this hurts friendliness**

Heavy RM count means heavy owner-scoping responsibility and heavy inline logic. Each RM is a hand-rolled class.

**Recommendation**

Consider consolidating or splitting the entity model. A 6-RM resource is a sign that the entity has too many concerns.

### 6. 13 Resources, 11 Pages, 6 Widgets — biggest surface in the audit set

**Why this hurts friendliness**

The package is huge. With the most resources of any Filament package, the wiring is complex.

**Recommendation**

Audit whether all 13 resources need to be Filament resources. Some may be better as Pages or in a different surface.

### 7. Policies cover only 3 of 13 resources

**Files**

- Only `AffiliateConversionPolicy`, `AffiliateFraudSignalPolicy`, `AffiliatePayoutPolicy`

**Why this hurts friendliness**

The remaining 10 resources (Affiliate, Program, Link, Network, etc.) have no policy. Authorization falls back to Filament defaults or `Gate::policy` from elsewhere.

**Recommendation**

Add policies for all resources that handle sensitive operations. Document the gaps.

## Concrete refactor plan

### Phase 1 — replace local owner-scope helper

**Steps**

1. Replace `Support/OwnerScopedQuery.php` with `commerce-support`'s `OwnerQuery` or `OwnerWriteGuard`.
2. Delete the local helper.

### Phase 2 — move bridges to the domain package

**Steps**

1. Move `Support/Integrations/CartBridge.php` and `VoucherBridge.php` to `affiliates`.
2. Re-import in the Filament package.

### Phase 3 — generalize portal pages

**Steps**

1. Audit the 8 portal pages.
2. Extract a `Concerns/InteractsWithPortalSession` trait or a generic `PortalPage`.
3. Refactor the portal pages.

### Phase 4 — add policies for the missing 10 resources

**Steps**

1. List resources without policies.
2. Add policies for sensitive ones (Affiliate, Program, PayoutMethod, etc.).
3. Bind in the service provider.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — replace local owner-scope helper

- [pending] Replace `Support/OwnerScopedQuery.php` with `commerce-support`'s `OwnerQuery` or `OwnerWriteGuard`.
- [pending] Delete the local helper.

### Phase 2 — move bridges to the domain package

- [pending] Move `Support/Integrations/CartBridge.php` and `VoucherBridge.php` to `affiliates`.
- [pending] Re-import in the Filament package.

### Phase 3 — generalize portal pages

- [pending] Audit the 8 portal pages.
- [pending] Extract a `Concerns/InteractsWithPortalSession` trait or a generic `PortalPage`.
- [pending] Refactor the portal pages.

### Phase 4 — add policies for the missing 10 resources

- [pending] List resources without policies.
- [pending] Add policies for sensitive ones (Affiliate, Program, PayoutMethod, etc.).
- [pending] Bind in the service provider.



## Suggested verification scope

- per-Resource tests
- portal page tests
- Widget tests
- cross-package tests for affiliates/affiliate-network/checkout

## Recommended first move

Phase 1 — replace local owner-scope helper. This is a one-file fix that aligns the package with the monorepo convention.
