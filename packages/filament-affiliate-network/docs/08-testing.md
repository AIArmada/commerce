---
title: Testing
---

# Testing

Guide to testing the Filament Affiliate Network plugin.

## Setup

### Required Dependencies

```php
// composer.json (dev dependencies)
"require-dev": {
    "pestphp/pest": "^5.2",
    "pestphp/pest-plugin-livewire": "^5.0",
    "livewire/livewire": "^4.4"
}
```

### Test Case Setup

```php
<?php

namespace Tests;

use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

abstract class FilamentTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);

        // Every shipped page, table, and widget gates on NetworkAdminAccess.
        Gate::define(config('filament-affiliate-network.authorization.admin_ability'), fn () => true);
    }
}
```

> **warning:**
> Resources, pages, and widgets are all gated by
> `NetworkAdminAccess::allows()`, which reads
> `filament-affiliate-network.authorization.admin_ability`
> (`affiliate-network.admin`). Without granting that ability, page and widget
> tests abort with `403` before rendering. `NetworkAdminAccess` has no
> `grant()` helper — define the Gate ability in your test case.

---

## Testing Resources

### AffiliateSiteResource Tests

```php
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;

use function Pest\Livewire\livewire;

it('can render sites list page', function () {
    livewire(AffiliateSiteResource\Pages\ListAffiliateSites::class)
        ->assertSuccessful();
});

it('can list sites', function () {
    $sites = AffiliateSite::factory()->count(3)->create();

    livewire(AffiliateSiteResource\Pages\ListAffiliateSites::class)
        ->assertCanSeeTableRecords($sites);
});

it('can render create page', function () {
    livewire(AffiliateSiteResource\Pages\CreateAffiliateSite::class)
        ->assertSuccessful();
});

it('can create site', function () {
    livewire(AffiliateSiteResource\Pages\CreateAffiliateSite::class)
        ->fillForm([
            'name' => 'Test Store',
            'domain' => 'teststore.com',
            'description' => 'A test store',
            'status' => AffiliateSite::STATUS_PENDING,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(AffiliateSite::where('domain', 'teststore.com')->exists())->toBeTrue();
});

it('validates unique domain', function () {
    AffiliateSite::factory()->create(['domain' => 'existing.com']);

    livewire(AffiliateSiteResource\Pages\CreateAffiliateSite::class)
        ->fillForm([
            'name' => 'Another Store',
            'domain' => 'existing.com',
        ])
        ->call('create')
        ->assertHasFormErrors(['domain' => 'unique']);
});

it('can render edit page', function () {
    $site = AffiliateSite::factory()->create();

    livewire(AffiliateSiteResource\Pages\EditAffiliateSite::class, [
        'record' => $site->id,
    ])
        ->assertSuccessful();
});

it('can update site', function () {
    $site = AffiliateSite::factory()->create();

    livewire(AffiliateSiteResource\Pages\EditAffiliateSite::class, [
        'record' => $site->id,
    ])
        ->fillForm([
            'name' => 'Updated Name',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($site->fresh()->name)->toBe('Updated Name');
});

it('can verify pending site via action', function () {
    $site = AffiliateSite::factory()->pending()->create();

    livewire(AffiliateSiteResource\Pages\ListAffiliateSites::class)
        ->callTableAction('verify', $site);

    expect($site->fresh())
        ->status->toBe(AffiliateSite::STATUS_VERIFIED)
        ->verified_at->not->toBeNull();
});

it('hides verify action for verified sites', function () {
    $site = AffiliateSite::factory()->verified()->create();

    livewire(AffiliateSiteResource\Pages\ListAffiliateSites::class)
        ->assertTableActionHidden('verify', $site);
});
```

### AffiliateOfferResource Tests

```php
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;

use function Pest\Livewire\livewire;

it('can render offers list page', function () {
    livewire(AffiliateOfferResource\Pages\ListAffiliateOffers::class)
        ->assertSuccessful();
});

it('can list offers', function () {
    $offers = AffiliateOffer::factory()->count(3)->create();

    livewire(AffiliateOfferResource\Pages\ListAffiliateOffers::class)
        ->assertCanSeeTableRecords($offers);
});

it('can create offer', function () {
    $site = AffiliateSite::factory()->verified()->create();

    livewire(AffiliateOfferResource\Pages\CreateAffiliateOffer::class)
        ->fillForm([
            'site_id' => $site->id,
            'name' => 'Summer Sale',
            'slug' => 'summer-sale',
            'rate_base_bp' => 1000,
            'status' => OfferStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(AffiliateOffer::where('slug', 'summer-sale')->exists())->toBeTrue();
});

it('can publish an archived offer via the activate action', function () {
    $offer = AffiliateOffer::factory()->archived()->create();

    livewire(AffiliateOfferResource\Pages\ListAffiliateOffers::class)
        ->callTableAction('activate', $offer);

    expect($offer->fresh()->status)->toBe(OfferStatus::Published);
});

it('can archive a published offer via the pause action', function () {
    $offer = AffiliateOffer::factory()->published()->create();

    livewire(AffiliateOfferResource\Pages\ListAffiliateOffers::class)
        ->callTableAction('pause', $offer);

    expect($offer->fresh()->status)->toBe(OfferStatus::Archived);
});

it('filters offers by status', function () {
    $published = AffiliateOffer::factory()->published()->create();
    $archived = AffiliateOffer::factory()->archived()->create();

    livewire(AffiliateOfferResource\Pages\ListAffiliateOffers::class)
        ->filterTable('status', OfferStatus::Published->value)
        ->assertCanSeeTableRecords([$published])
        ->assertCanNotSeeTableRecords([$archived]);
});
```

> **warning:**
> `AffiliateOffer` declares no `STATUS_*` constants and there is no `active` /
> `paused` status. `OfferStatus` is `Draft | Published | Archived`, and the
> `activate` / `pause` actions map onto `Published` / `Archived`. The factory
> has no `active()` or `paused()` state either.

### AffiliateOfferApplicationResource Tests

```php
use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource;

use function Pest\Livewire\livewire;

it('can render applications list page', function () {
    livewire(AffiliateOfferApplicationResource\Pages\ListAffiliateOfferApplications::class)
        ->assertSuccessful();
});

it('can approve pending application', function () {
    $application = AffiliateOfferApplication::factory()->pending()->create();

    livewire(AffiliateOfferApplicationResource\Pages\ListAffiliateOfferApplications::class)
        ->callTableAction('approve', $application);

    expect($application->fresh())
        ->status->toBe(ApplicationStatus::Approved)
        ->reviewed_at->not->toBeNull();
});

it('can reject application with reason', function () {
    $application = AffiliateOfferApplication::factory()->pending()->create();

    livewire(AffiliateOfferApplicationResource\Pages\ListAffiliateOfferApplications::class)
        ->callTableAction('reject', $application, [
            'reason' => 'Traffic sources not aligned',
        ]);

    expect($application->fresh())
        ->status->toBe(ApplicationStatus::Rejected)
        ->rejection_reason->toBe('Traffic sources not aligned');
});

it('can revoke approved application', function () {
    $application = AffiliateOfferApplication::factory()->approved()->create();

    livewire(AffiliateOfferApplicationResource\Pages\ListAffiliateOfferApplications::class)
        ->callTableAction('revoke', $application, [
            'reason' => 'Policy violation',
        ]);

    expect($application->fresh())
        ->status->toBe(ApplicationStatus::Revoked)
        ->rejection_reason->toBe('Policy violation');
});

it('can bulk approve applications', function () {
    $applications = AffiliateOfferApplication::factory()
        ->pending()
        ->count(3)
        ->create();

    livewire(AffiliateOfferApplicationResource\Pages\ListAffiliateOfferApplications::class)
        ->callTableBulkAction('approve_selected', $applications);

    foreach ($applications as $application) {
        expect($application->fresh()->status)->toBe(ApplicationStatus::Approved);
    }
});
```

> **warning:**
> `AffiliateOfferApplication` declares no `STATUS_*` constants. Read the
> `ApplicationStatus` enum (`Pending | Approved | Rejected | Revoked`).

---

## Testing Pages

The package ships one page: `MerchantDashboardPage`. There is no
marketplace/discovery page.

### Merchant Dashboard Page Tests

```php
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\FilamentAffiliateNetwork\Pages\MerchantDashboardPage;

use function Pest\Livewire\livewire;

it('can render merchant dashboard', function () {
    livewire(MerchantDashboardPage::class)
        ->assertSuccessful();
});

it('shows correct site counts', function () {
    AffiliateSite::factory()->verified()->count(3)->create();
    AffiliateSite::factory()->pending()->count(2)->create();

    $component = livewire(MerchantDashboardPage::class);

    expect($component->getSitesCount())->toBe(5);
    expect($component->getVerifiedSitesCount())->toBe(3);
});

it('shows correct offer counts', function () {
    AffiliateOffer::factory()->published()->count(5)->create();
    AffiliateOffer::factory()->archived()->count(3)->create();

    $component = livewire(MerchantDashboardPage::class);

    expect($component->getActiveOffersCount())->toBe(5);
});

it('shows pending applications count', function () {
    AffiliateOfferApplication::factory()->pending()->count(7)->create();
    AffiliateOfferApplication::factory()->approved()->count(3)->create();

    $component = livewire(MerchantDashboardPage::class);

    expect($component->getPendingApplicationsCount())->toBe(7);
});
```

---

## Testing Widgets

### NetworkStatsWidget Tests

```php
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\FilamentAffiliateNetwork\Widgets\NetworkStatsWidget;

use function Pest\Livewire\livewire;

it('can render network stats widget', function () {
    livewire(NetworkStatsWidget::class)
        ->assertSuccessful();
});

it('displays correct statistics', function () {
    AffiliateSite::factory()->verified()->count(3)->create();
    AffiliateOffer::factory()->published()->count(5)->create();
    AffiliateOfferApplication::factory()->pending()->count(2)->create();
    AffiliateOfferLink::factory()->withStats(1000, 50, 250000)->create();

    $component = livewire(NetworkStatsWidget::class);
    $stats = $component->getStats();

    expect($stats)->toHaveCount(6);
    // Active Sites, Active Offers, Pending Applications, Total Clicks, Conversion Rate, Total Revenue
});
```

> **warning:**
> `getStats()` is a `protected` method on the widget. Assert against rendered
> output or the `NetworkStatsAggregator::aggregate()` array instead of calling
> it through Livewire.

### TopOffersWidget Tests

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\FilamentAffiliateNetwork\Widgets\TopOffersWidget;

use function Pest\Livewire\livewire;

it('can render top offers widget', function () {
    livewire(TopOffersWidget::class)
        ->assertSuccessful();
});

it('displays offers ordered by clicks', function () {
    $lowClicks = AffiliateOffer::factory()->published()->create();
    AffiliateOfferLink::factory()->forOffer($lowClicks)->withStats(100, 5, 5000)->create();

    $highClicks = AffiliateOffer::factory()->published()->create();
    AffiliateOfferLink::factory()->forOffer($highClicks)->withStats(1000, 50, 50000)->create();

    livewire(TopOffersWidget::class)
        ->assertCanSeeTableRecords([$highClicks, $lowClicks]);
});
```

> **warning:**
> `TopOffersWidget` caches its top-10 offer ids for 30 seconds via
> `OwnerCache`. Call
> `OwnerCache::forget(null, 'affiliate-network.top-offer-ids')` between
> assertions, or the first test's ids will be reused.

---

## Testing Tips

### Authenticate Before Tests

```php
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});
```

### Test With Tenancy

```php
it('scopes resources to current tenant', function () {
    $tenant = Tenant::factory()->create();
    
    $ownSite = AffiliateSite::factory()->forOwner($tenant)->create();
    $otherSite = AffiliateSite::factory()->create();

    setCurrentTenant($tenant);

    livewire(AffiliateSiteResource\Pages\ListAffiliateSites::class)
        ->assertCanSeeTableRecords([$ownSite])
        ->assertCanNotSeeTableRecords([$otherSite]);
});
```

### Assert Notifications

```php
use Filament\Notifications\Notification;

it('shows success notification on approve', function () {
    $application = AffiliateOfferApplication::factory()->pending()->create();

    livewire(AffiliateOfferApplicationResource\Pages\ListAffiliateOfferApplications::class)
        ->callTableAction('approve', $application)
        ->assertNotified('Application approved');
});
```

## Real-Chrome Smoke Test

Pest covers wiring; `demo/tests/chrome/affiliate-surfaces.mjs` proves the
surfaces in real Chrome via Puppeteer: login, offers table (fee column),
offer Legs tab + leg reverse, site token rotation, conversions table
(origin/source ref) + conversion reverse. Deterministic `CHROME-*`
fixtures come from `demo/database/seeders/ChromeSmokeSeeder.php`, which
resets its own rows on every run.

```bash
# From demo/, against a migrated + served app (Herd or artisan serve):
npm run chrome:affiliates
BASE_URL=http://127.0.0.1:8000 npm run chrome:affiliates
node tests/chrome/affiliate-surfaces.mjs --no-seed  # skip re-seeding
```

Screenshots land in `demo/tests/chrome/screenshots/` (gitignored). The
script exits non-zero with the failing check names.
