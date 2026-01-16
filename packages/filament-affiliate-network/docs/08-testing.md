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
    "pestphp/pest": "^3.0",
    "pestphp/pest-plugin-livewire": "^3.0",
    "livewire/livewire": "^3.0"
}
```

### Test Case Setup

```php
<?php

namespace Tests;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class FilamentTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }
}
```

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
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'status' => AffiliateOffer::STATUS_DRAFT,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(AffiliateOffer::where('slug', 'summer-sale')->exists())->toBeTrue();
});

it('can activate offer via action', function () {
    $offer = AffiliateOffer::factory()->paused()->create();

    livewire(AffiliateOfferResource\Pages\ListAffiliateOffers::class)
        ->callTableAction('activate', $offer);

    expect($offer->fresh()->status)->toBe(AffiliateOffer::STATUS_ACTIVE);
});

it('can pause offer via action', function () {
    $offer = AffiliateOffer::factory()->active()->create();

    livewire(AffiliateOfferResource\Pages\ListAffiliateOffers::class)
        ->callTableAction('pause', $offer);

    expect($offer->fresh()->status)->toBe(AffiliateOffer::STATUS_PAUSED);
});

it('filters offers by status', function () {
    $active = AffiliateOffer::factory()->active()->create();
    $paused = AffiliateOffer::factory()->paused()->create();

    livewire(AffiliateOfferResource\Pages\ListAffiliateOffers::class)
        ->filterTable('status', AffiliateOffer::STATUS_ACTIVE)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$paused]);
});
```

### AffiliateOfferApplicationResource Tests

```php
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
        ->status->toBe(AffiliateOfferApplication::STATUS_APPROVED)
        ->reviewed_at->not->toBeNull();
});

it('can reject application with reason', function () {
    $application = AffiliateOfferApplication::factory()->pending()->create();

    livewire(AffiliateOfferApplicationResource\Pages\ListAffiliateOfferApplications::class)
        ->callTableAction('reject', $application, [
            'reason' => 'Traffic sources not aligned',
        ]);

    expect($application->fresh())
        ->status->toBe(AffiliateOfferApplication::STATUS_REJECTED)
        ->rejection_reason->toBe('Traffic sources not aligned');
});

it('can revoke approved application', function () {
    $application = AffiliateOfferApplication::factory()->approved()->create();

    livewire(AffiliateOfferApplicationResource\Pages\ListAffiliateOfferApplications::class)
        ->callTableAction('revoke', $application, [
            'reason' => 'Policy violation',
        ]);

    expect($application->fresh())
        ->status->toBe(AffiliateOfferApplication::STATUS_REVOKED)
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
        expect($application->fresh()->status)->toBe(AffiliateOfferApplication::STATUS_APPROVED);
    }
});
```

---

## Testing Pages

### Marketplace Page Tests

```php
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\FilamentAffiliateNetwork\Pages\AffiliateMarketplacePage;
use AIArmada\Affiliates\Models\Affiliate;

use function Pest\Livewire\livewire;

it('can render marketplace page', function () {
    livewire(AffiliateMarketplacePage::class)
        ->assertSuccessful();
});

it('lists active public offers', function () {
    $activePublic = AffiliateOffer::factory()->active()->create(['is_public' => true]);
    $activePrivate = AffiliateOffer::factory()->active()->create(['is_public' => false]);
    $pausedPublic = AffiliateOffer::factory()->paused()->create(['is_public' => true]);

    $offers = livewire(AffiliateMarketplacePage::class)
        ->call('getOffers');

    expect($offers->contains($activePublic))->toBeTrue();
    expect($offers->contains($activePrivate))->toBeFalse();
    expect($offers->contains($pausedPublic))->toBeFalse();
});

it('can search offers', function () {
    AffiliateOffer::factory()->active()->create([
        'name' => 'Summer Sale Campaign',
        'is_public' => true,
    ]);
    AffiliateOffer::factory()->active()->create([
        'name' => 'Winter Promo',
        'is_public' => true,
    ]);

    $component = livewire(AffiliateMarketplacePage::class)
        ->set('search', 'Summer');

    $offers = $component->call('getOffers');
    expect($offers)->toHaveCount(1);
    expect($offers->first()->name)->toBe('Summer Sale Campaign');
});

it('can filter by category', function () {
    $category = AffiliateOfferCategory::factory()->create();
    
    $inCategory = AffiliateOffer::factory()->active()->create([
        'category_id' => $category->id,
        'is_public' => true,
    ]);
    $noCategory = AffiliateOffer::factory()->active()->create([
        'category_id' => null,
        'is_public' => true,
    ]);

    $component = livewire(AffiliateMarketplacePage::class)
        ->set('categoryFilter', $category->id);

    $offers = $component->call('getOffers');
    expect($offers)->toHaveCount(1);
    expect($offers->first()->id)->toBe($inCategory->id);
});

it('can apply for offer', function () {
    $offer = AffiliateOffer::factory()->active()->create(['is_public' => true]);
    $affiliate = Affiliate::factory()->create(['contact_email' => $this->admin->email]);

    livewire(AffiliateMarketplacePage::class)
        ->call('applyForOffer', $offer->id, 'I want to promote this');

    expect(AffiliateOfferApplication::where([
        'offer_id' => $offer->id,
        'affiliate_id' => $affiliate->id,
    ])->exists())->toBeTrue();
});
```

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
    AffiliateOffer::factory()->active()->count(5)->create();
    AffiliateOffer::factory()->paused()->count(3)->create();

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
    AffiliateOffer::factory()->active()->count(5)->create();
    AffiliateOfferApplication::factory()->pending()->count(2)->create();
    AffiliateOfferLink::factory()->withStats(1000, 50, 250000)->create();

    $component = livewire(NetworkStatsWidget::class);
    $stats = $component->getStats();

    expect($stats)->toHaveCount(6);
    // Active Sites, Active Offers, Pending Applications, Total Clicks, Conversion Rate, Total Revenue
});
```

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
    $lowClicks = AffiliateOffer::factory()->active()->create();
    AffiliateOfferLink::factory()->forOffer($lowClicks)->withStats(100, 5, 5000)->create();

    $highClicks = AffiliateOffer::factory()->active()->create();
    AffiliateOfferLink::factory()->forOffer($highClicks)->withStats(1000, 50, 50000)->create();

    livewire(TopOffersWidget::class)
        ->assertCanSeeTableRecords([$highClicks, $lowClicks]);
});
```

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
