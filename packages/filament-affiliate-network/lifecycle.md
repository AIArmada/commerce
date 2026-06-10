# Filament Affiliate Network — Lifecycle

## 1. Purpose

`filament-affiliate-network` is the **Filament admin UI and merchant marketplace** for the affiliate network domain. It delivers:

- **Admin CRUD** for Sites, Offers, Categories, and Applications via Filament resources.
- **Merchant Dashboard** — a page with network-wide stats widgets and top-offer leaderboards.
- **Affiliate Marketplace** — a public-facing page where affiliates browse active offers, apply, and generate tracking links.
- **Network-wide admin view** — every read/write path intentionally bypasses per-tenant owner scoping to operate on all tenant data globally.

The package is designed as a **global-admin-only surface**. It does not enforce per-tenant isolation; instead it provides a unified cross-tenant view for network administrators.

---

## 2. Dependencies

### Hard dependencies (must be present)

| Package | Models consumed |
|---|---|
| `aiarmada/affiliate-network` | `AffiliateSite`, `AffiliateOffer`, `AffiliateOfferCategory`, `AffiliateOfferApplication`, `AffiliateOfferLink` |
| `aiarmada/affiliates` | `Affiliate` |
| `aiarmada/commerce-support` | `OwnerContext`, `OwnerScope`, `MoneyFormatter` |
| `filament/filament` | Panel, Resources, Pages, Widgets |
| `spatie/laravel-package-tools` | `PackageServiceProvider` |

### Services consumed (from `affiliate-network`)

- `AIArmada\AffiliateNetwork\Services\OfferManagementService` — approve/reject/revoke applications, resolve public offers, check approval status.
- `AIArmada\AffiliateNetwork\Services\OfferLinkService` — create tracking links, generate tracking URLs.

### No migrations

This package has **no migrations** of its own. All persistence is delegated to `affiliate-network`.

---

## 3. Installation & Configuration

### Panel registration

Register the plugin on any Filament panel that needs the affiliate network admin:

```php
use AIArmada\FilamentAffiliateNetwork\FilamentAffiliateNetworkPlugin;

$panel->plugin(FilamentAffiliateNetworkPlugin::class);
```

### Config (`config/filament-affiliate-network.php`)

```php
return [
    'navigation' => [
        'group' => 'Affiliate Network',   // Navigation group label
        'sort'  => 50,                    // Base navigation sort order
    ],
    'marketplace' => [
        'show_commission_rates' => true,   // Show rates on marketplace cards
        'show_cookie_duration'  => true,   // Show cookie duration on marketplace cards
    ],
];
```

### Service provider boot sequence

1. `packageRegistered()` — binds `FilamentAffiliateNetworkPlugin` as a singleton.
2. `packageBooted()` — registers 4 model policies via `Gate::policy()`.

---

## 4. Core Concepts

### 4.1 Global-Admin-Only Architecture

Every resource overrides `getEloquentQuery()` to **remove owner scoping**:

| Resource | Scope bypass |
|---|---|
| `AffiliateSiteResource` | `->withoutOwnerScope()` |
| `AffiliateOfferResource` | `->withoutGlobalScope('owner_via_site')` |
| `AffiliateOfferCategoryResource` | `->withoutOwnerScope()` |
| `AffiliateOfferApplicationResource` | `->withoutGlobalScope('owner_via_affiliate')` |

Two resources also explicitly set `$tenantOwnershipRelationshipName = null` to disable Filament's built-in multi-tenancy (`AffiliateSiteResource`, `AffiliateOfferCategoryResource`).

All relationship eager-loads (e.g. `offer->site`, `application->affiliate`) also bypass scope so related models resolve correctly in a cross-tenant view.

### 4.2 Explicit Global Context

Whenever a write path or query must operate outside a tenant owner, the code wraps operations in:

```php
OwnerContext::withOwner(null, fn () => ...);
```

This is used in:
- `NetworkStatsAggregator::aggregate()`
- `AffiliateNetworkOptionsProvider` (all static methods)
- `AffiliateOffersTable` action handlers (activate/pause)
- `AffiliateSitesTable` verify action
- `CreateAffiliateOffer::handleRecordCreation()`
- `EditAffiliateOffer::handleRecordUpdate()`
- `CreateAffiliateOffer::mutateFormDataBeforeCreate()` (validates site/category existence)
- `EditAffiliateOffer::mutateFormDataBeforeSave()` (same)
- `AffiliateMarketplacePage` — all queries and application/link-generation ops

### 4.3 Resources

#### AffiliateSiteResource
- **Model**: `AffiliateSite`
- **Pages**: List, Create, Edit
- **Form sections**: Site Details (name, domain, description), Status (status, verification_method, verified_at), Settings (settings, metadata JSON)
- **Table actions**: Edit, Verify (sets status to `verified` with `verified_at`)
- **Filter**: status dropdown

#### AffiliateOfferResource
- **Model**: `AffiliateOffer`
- **Pages**: List, Create, Edit
- **Form sections**: Offer Details (site_id, category_id, name, slug, description, terms), Commission (type, rate in basis points, currency, cookie_days), Settings (status, is_featured, is_public, requires_approval, landing_url, starts_at, ends_at), Advanced (restrictions JSON, metadata JSON)
- **Table actions**: Edit, Activate, Pause
- **Filters**: status, site_id (cross-tenant), is_featured, is_public
- **Write path**: `mutateFormDataBeforeCreate`/`mutateFormDataBeforeSave` validates that `site_id` and `category_id` exist (resolved cross-tenant) before persisting. `handleRecordCreation`/`handleRecordUpdate` wrap in global context.

#### AffiliateOfferCategoryResource
- **Model**: `AffiliateOfferCategory`
- **Pages**: List (reorderable), Create, Edit
- **Form**: parent_id (self-excluding), name, slug, icon, description, sort_order, is_active
- **Table**: reorderable by `sort_order`, parent filter (cross-tenant)

#### AffiliateOfferApplicationResource
- **Model**: `AffiliateOfferApplication`
- **Pages**: List, View (no Create — applications originate from the marketplace page)
- **Form**: offer_id, affiliate_id (disabled), status, reason (disabled), rejection_reason, reviewed_by (disabled), reviewed_at (disabled)
- **Infolist**: all detail fields rendered for view page
- **Table actions**: Approve, Reject (with reason form), Revoke (with reason form), View
- **Bulk action**: Approve Selected
- **All state-change actions** delegate to `OfferManagementService::approveApplication()` / `rejectApplication()` / `revokeApplication()`

### 4.4 Pages

#### MerchantDashboardPage
- **Slug**: `affiliate-network/merchant-dashboard`
- **View**: `filament-affiliate-network::pages.merchant-dashboard`
- **Header widgets**: `NetworkStatsWidget`, `TopOffersWidget`
- **Exposes data methods for the Blade view**: `getStats()`, `getRecentApplications()`, `getTopOffers()`, `getSitesCount()`, `getVerifiedSitesCount()`, `getActiveOffersCount()`, `getPendingApplicationsCount()`
- All queries operate in explicit global context.

#### AffiliateMarketplacePage
- **Slug**: `affiliate-network/marketplace`
- **View**: `filament-affiliate-network::pages.affiliate-marketplace`
- **Livewire properties**: `search` (string), `categoryFilter` (nullable string), `sortBy` (string: featured/newest/commission)
- **Resolves the authenticated user's `Affiliate` record** by matching `contact_email` to the user's email, in global context (not per-tenant).
- **Key user actions**:
  - `applyForOffer($offerId, $reason)` — delegates to `OfferManagementService::applyForOffer()`
  - `generateLink($offerId)` — delegates to `OfferLinkService::createLink()` and `generateTrackingUrl()`
  - Both check `requires_approval` flag on the offer and approval status before acting
- **Exposes data methods for the Blade view**: `getCategories()`, `getOffers()`, `getAffiliate()`, `hasApplied()`, `getApplicationStatus()`

### 4.5 Widgets

#### NetworkStatsWidget (`StatsOverviewWidget`)
Sort: 1. Aggregates via `NetworkStatsAggregator` and renders 6 stat cards: Active Sites, Active Offers, Pending Applications, Total Clicks, Conversion Rate, Total Revenue.

#### TopOffersWidget (`TableWidget`)
Sort: 2. Shows top 10 active offers ranked by total clicks. Columns: name, site, clicks, conversions, revenue, commission rate. Not paginated.

### 4.6 Support Classes

#### NetworkStatsAggregator
Runs in explicit global context (`OwnerContext::withOwner(null, ...)`). Aggregates:
- `activeSites` — count of verified sites
- `activeOffers` — count of active offers
- `pendingApplications` — count of pending applications
- `totalClicks` / `totalConversions` / `totalRevenue` — sums from `AffiliateOfferLink`
- `conversionRate` — (conversions / clicks) × 100
- `revenueFormatted` — via `MoneyFormatter::formatMinor($totalRevenue, 'USD')`

#### AffiliateNetworkOptionsProvider
Provides `pluck('name', 'id')` option arrays for form selects. All queries bypass owner scope. Three static methods:
- `verifiedSiteOptions()` — verified sites only
- `activeCategoryOptions()` — active categories only
- `parentCategoryOptions(?string $excludeId)` — all categories, optionally excluding one ID

### 4.7 Policies

All four policies (`AffiliateSitePolicy`, `AffiliateOfferPolicy`, `AffiliateOfferCategoryPolicy`, `AffiliateOfferApplicationPolicy`) allow every action (`viewAny`, `view`, `create`, `update`, `delete`, `deleteAny`, `restore`, `forceDelete`). Authorization is expected to be enforced at the panel/route level (e.g. admin-only middleware) rather than per-model.

---

## 5. Request Lifecycle

### 5.1 Admin CRUD (List/Create/Edit/View)

```
HTTP Request
  └─ Filament routing resolves resource + page class
       └─ getEloquentQuery() removes owner scope
            └─ Table/Form/Schema configured via companion class
                 └─ On write: Create/Edit page mutations:
                      ├─ mutateFormDataBeforeCreate/BeforeSave: validates foreign IDs
                      │    cross-tenant via OwnerContext::withOwner(null, ...)
                      └─ handleRecordCreation/handleRecordUpdate: wrapped in
                           OwnerContext::withOwner(null, ...) to prevent owner-scope
                           hooks from rejecting the operation
```

### 5.2 Table Action (verify/activate/pause)

```
User clicks action button
  └─ Action handler resolves the record in global context
       └─ OwnerContext::withOwner(null, fn () => Model::query()
            ->whereKey($record->getKey())->firstOrFail())
            └─ Updates status field directly
```

### 5.3 Application State Change (approve/reject/revoke)

```
User clicks approve/reject/revoke
  └─ Table action captures reviewer name from auth()->user()
       └─ Delegates to OfferManagementService::approveApplication()
          / rejectApplication() / revokeApplication()
            └─ Service handles domain logic, state transition, and events
                 └─ Notification sent to the admin UI
```

### 5.4 Marketplace Lifecycle

```
Affiliate visits /affiliate-network/marketplace
  └─ Page boots, resolves Affiliate from auth email (global context)
       └─ getOffers() loads active public offers with eager-loaded site/category
            └─ User browses, searches, filters, sorts
                 ├─ Clicks Apply → applyForOffer()
                 │    ├─ OfferManagementService::resolvePublicOfferOrFail()
                 │    ├─ Checks requires_approval flag
                 │    ├─ If no approval needed → generateLink() directly
                 │    └─ If approval needed → OfferManagementService::applyForOffer()
                 │         └─ Notification sent
                 └─ Clicks Generate Link → generateLink()
                      ├─ OfferManagementService::isApprovedForOffer()
                      └─ OfferLinkService::createLink() + generateTrackingUrl()
                           └─ Notification with tracking URL displayed
```

### 5.5 Widget Rendering (Merchant Dashboard)

```
Page loads → getHeaderWidgets() returns widget classes
  ├─ NetworkStatsWidget::getStats()
  │    └─ NetworkStatsAggregator::aggregate()
  │         └─ 5 cross-tenant aggregate queries in OwnerContext::withOwner(null, ...)
  └─ TopOffersWidget::table()
       └─ Single query: top 10 active offers with link sums, in global context
```

---

## 6. Extension Points

### 6.1 Config-Driven

- `navigation.group` / `navigation.sort` — control where and how the package appears in the Filament sidebar.
- `marketplace.show_commission_rates` / `marketplace.show_cookie_duration` — toggle visibility of commission/cookie info on marketplace cards (consumed by the Blade view).

### 6.2 Overridable Methods on Plugin

`FilamentAffiliateNetworkPlugin::boot(Panel $panel)` is empty — use it to add panel-level configuration (middleware, auth guards, etc.) without subclassing.

### 6.3 Policies Are Replaceable

Each policy is registered via `Gate::policy()` in the service provider. Replace any policy by re-registering after the service provider boots or by using a deferred provider.

### 6.4 Views Are Publishable

Views live under `filament-affiliate-network::pages.merchant-dashboard` and `filament-affiliate-network::pages.affiliate-marketplace`. Publish with `php artisan vendor:publish --tag=filament-affiliate-network-views`.

### 6.5 Foreign ID Validation Hooks

`CreateAffiliateOffer` and `EditAffiliateOffer` override `mutateFormDataBeforeCreate`/`mutateFormDataBeforeSave` to validate `site_id` and `category_id` cross-tenant. A subclass could add additional validation or transformation.

### 6.6 Service Delegation

Marketplace and application actions delegate to the `affiliate-network` package's services (`OfferManagementService`, `OfferLinkService`). Custom behaviour is plugged in at the service layer, not reimplemented in the Filament package.

### 6.7 No Events or Listeners

This package does not emit or listen to domain events. All state-change notifications are Filament `Notification` objects shown in the admin UI.

---

## 7. Testing

### 7.1 What to test

Tests belong in the `affiliate-network` package's test suite or the application's Filament test suite. This package has no test directory of its own.

### 7.2 Filament test helpers

Use Filament's testing helpers to verify resource behaviour:

```php
use function Pest\Livewire\livewire;

// List page
livewire(ListAffiliateSites::class)
    ->assertCanSeeTableRecords($sites)
    ->assertCanRenderTableColumn('domain');

// Create page
livewire(CreateAffiliateSite::class)
    ->fillForm(['name' => 'Example', 'domain' => 'example.com'])
    ->call('create')
    ->assertHasNoFormErrors();

// Verify action
livewire(ListAffiliateSites::class)
    ->callTableAction('verify', $pendingSite)
    ->assertNotified();
```

### 7.3 Key verification areas

1. **Cross-tenant isolation**: Verify a record owned by tenant A is visible and editable in the admin panel (by design) but verify that write paths enforce correct owner assignment via `affiliate-network` services.
2. **Marketplace flow**: Test apply, generate-link, and approval-required workflows with a resolved affiliate.
3. **Application state machine**: Test approve/reject/revoke actions against the service layer.
4. **Widget rendering**: Verify stats aggregation returns correct counts and sums.
5. **Form validation**: Test foreign ID lookups (site_id, category_id, parent_id) succeed and fail correctly across tenants.
