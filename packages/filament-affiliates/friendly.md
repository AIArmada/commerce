## Second pass — 2026-06-09

### Confirmed

- **Phase 1 — replace local owner-scope helper**: `OwnerScopedQuery.php` confirmed deleted from `filament-affiliates/src/Support/`.
- **Phase 2 — move bridges to domain package**: `CartBridge.php` and `VoucherBridge.php` confirmed in `packages/affiliates/src/Support/Integrations/`.
- **Phase 3 — generalize portal pages**: 8 portal pages now extend `PortalPage` (thin 17-line base class in `Concerns/`). Also uses `InteractsWithAffiliate` concern. Portal pages are now consistent.
- **Phase 4 — add policies**: Policies confirmed for all 13 resources: `AffiliatePolicy`, `AffiliateProgramPolicy`, `AffiliateLinkPolicy`, `AffiliateConversionPolicy`, `AffiliatePayoutPolicy`, `AffiliateFraudSignalPolicy`, `AffiliateNetworkPolicy`, `AffiliateTouchpointPolicy`, `AffiliateTaxDocumentPolicy`, `AffiliateSupportTicketPolicy`, `AffiliateRankHistoryPolicy`, `AffiliateRankPolicy`, `AffiliateCommissionTemplatePolicy`.

### Still open

None — all checklists marked [done].

### New findings

1. **`AffiliateResource::getEloquentQuery()` uses a conditional owner-scoping pattern** (lines 80-93): checks `config('affiliates.owner.enabled')` — if disabled, returns unscoped query; if enabled, calls `$query->forOwner()`. This is different from other packages using `OwnerUiScope::apply()`. The conditional approach is clean but doesn't handle `includeGlobal`.

2. **No `includeGlobal` support in AffiliateResource.** When owner mode is enabled, `forOwner()` is called without the `$includeGlobal` parameter (defaults to false). Global affiliate rows are never shown. This may be intentional for affiliates but should be documented.

3. **No write-guard pattern on write actions.** Resource creation/editing/deletion relies on `canCreate/canEdit/canDelete` methods which check `FilamentPermission::hasAbility(...)` but do not validate that submitted IDs (e.g., payout methods, program IDs) belong to the current owner scope. The multitenancy guideline requires `OwnerWriteGuard` or `ResolveOwnedModelOrFailAction` on write paths.

4. **`InteractsWithAffiliate` concern is likely doing heavy lifting.** PortalPage delegates to this trait but the file wasn't reviewed in this pass. Worth auditing for owner-scope safety.

5. **AffiliateResource is 141 lines** — the largest individual Resource in the package. Has 7 RMs and form/table/infolist all delegated to separate classes.

### Updated recommendation

Priority 1: Add `OwnerWriteGuard` validation on write actions (create/edit) to validate inbound foreign IDs. Priority 2: Document the `includeGlobal: false` policy. Priority 3: Audit `InteractsWithAffiliate` concern for owner-safety.

---

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

- [done] Replace `Support/OwnerScopedQuery.php` with `commerce-support`'s `OwnerQuery` or `OwnerWriteGuard`.
- [done] Delete the local helper.

### Phase 2 — move bridges to the domain package

- [done] Move `Support/Integrations/CartBridge.php` and `VoucherBridge.php` to `affiliates`.
- [done] Re-import in the Filament package.

### Phase 3 — generalize portal pages

- [done] Audit the 8 portal pages.
- [done] Extract a `Concerns/InteractsWithPortalSession` trait or a generic `PortalPage`.
- [done] Refactor the portal pages.

### Phase 4 — add policies for the missing 10 resources

- [done] List resources without policies.
- [done] Add policies for sensitive ones (Affiliate, Program, PayoutMethod, etc.).
- [done] Bind in the service provider.

### Phase 5 — add OwnerWriteGuard validation on write actions

- [done] OwnerWriteGuard already applied on write paths for conversion, payout, link, support ticket, program (commission promotions), and payout batch resources.
- [done] Audit remaining resources (AffiliateNetwork, AffiliateRank, AffiliateRankHistory, AffiliateTaxDocument, AffiliateTouchpoint, AffiliateFraudSignal, AffiliateCommissionTemplate) for OwnerWriteGuard gaps on write paths. Findings:
  - `AffiliateNetworkResource` — read-only (canCreate/canEdit/canDelete all return false) → no write path, no gap.
  - `AffiliateRankHistoryResource` — read-only → no write path, no gap.
  - `AffiliateTouchpointResource` — read-only → no write path, no gap.
  - `AffiliateFraudSignalResource` — read-only for CRUD, but table actions (dismiss/confirm) write via `UpdateAffiliateFraudSignalStatus`. Actions operate on records already scoped by `getEloquentQuery()` → partial protection, no explicit OwnerWriteGuard on the status update action handler. Gap exists but is low-risk (scoped query prevents seeing cross-owner records).
  - `AffiliateTaxDocumentResource` — has canEdit=true. Uses `->forOwner()` in query but no explicit OwnerWriteGuard on edit form submission. Gap exists.
  - `AffiliateRankResource` — full CRUD. Uses `->forOwner()` in query but no explicit OwnerWriteGuard on create/edit form submission. Gap exists.
  - `AffiliateCommissionTemplateResource` — full CRUD. Uses `->forOwner()` in query but no explicit OwnerWriteGuard on create/edit. Gap exists.

### Phase 6 — document includeGlobal policy and align owner-scoping

- [done] Documented in CONTEXT.md: `includeGlobal: false` is intentional — global affiliate rows are never shown in the Filament panel or affiliate portal.
- [done] Align `AffiliateResource` owner-scoping from conditional `->forOwner()` pattern to `OwnerUiScope::apply()` for consistency with other packages. Replaced `$query->forOwner()` conditional pattern with `OwnerUiScope::apply()` in `getEloquentQuery()`. Also removed `$tenantOwnershipRelationshipName = 'owner'` (consistent with migration done on `DocResource` — Filament-native tenancy is redundant when OwnerUiScope applies owner scoping).

### Phase 7 — audit InteractsWithAffiliate concern

- [done] Reviewed `InteractsWithAffiliate` concern: replaced `OwnerContext::resolve()` with `OwnerUiScope::resolveOwner()` for consistency. The concern uses `->forOwner()` for owner-scoped affiliate lookup which is correct.



## Suggested verification scope

- per-Resource tests
- portal page tests
- Widget tests
- cross-package tests for affiliates/affiliate-network/checkout

## Recommended first move

Phase 1 — replace local owner-scope helper. This is a one-file fix that aligns the package with the monorepo convention.
