---
title: Testing
---

# Testing

Guide to testing the Filament Affiliate Network plugin with Pest + Livewire.

## Setup

```php
// composer.json (dev)
"require-dev": {
    "pestphp/pest": "^5.2",
    "pestphp/pest-plugin-livewire": "^5.0",
    "livewire/livewire": "^4.4"
}
```

```php
<?php

namespace Tests;

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

        Gate::define(
            config('filament-affiliate-network.authorization.admin_ability'),
            fn (): bool => true,
        );
    }
}
```

> **warning**
> Resources, pages, and widgets gate on `NetworkAdminAccess::allows()`, which
> reads `filament-affiliate-network.authorization.admin_ability`
> (`affiliate-network.admin`). Without defining that ability, page/widget tests
> abort with `403` before rendering. `NetworkAdminAccess` has no `grant()` helper.

## Testing resources

### Sites

```php
use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;

use function Pest\Livewire\livewire;

it('renders the sites list', function (): void {
    livewire(AffiliateSiteResource\Pages\ListAffiliateSites::class)->assertSuccessful();
});

it('creates a site', function (): void {
    livewire(AffiliateSiteResource\Pages\CreateAffiliateSite::class)
        ->fillForm([
            'name' => 'Test Store',
            'domain' => 'teststore.com',
            'status' => SiteStatus::Pending->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(AffiliateSite::where('domain', 'teststore.com')->exists())->toBeTrue();
});

it('validates the bare domain rule', function (): void {
    livewire(AffiliateSiteResource\Pages\CreateAffiliateSite::class)
        ->fillForm(['name' => 'Bad', 'domain' => 'https://example.com/path'])
        ->call('create')
        ->assertHasFormErrors(['domain']);
});

it('verifies a pending site via the action', function (): void {
    $site = AffiliateSite::factory()->pending()->create();

    livewire(AffiliateSiteResource\Pages\ListAffiliateSites::class)
        ->callTableAction('verify', $site);

    expect($site->fresh()->isVerified())->toBeTrue();
});
```

### Offers

```php
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;

it('creates an offer', function (): void {
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

it('publishes an archived offer via activate', function (): void {
    $offer = AffiliateOffer::factory()->archived()->create();

    livewire(AffiliateOfferResource\Pages\ListAffiliateOffers::class)
        ->callTableAction('activate', $offer);

    expect($offer->fresh()->status)->toBe(OfferStatus::Published);
});

it('archives a published offer via pause', function (): void {
    $offer = AffiliateOffer::factory()->published()->create();

    livewire(AffiliateOfferResource\Pages\ListAffiliateOffers::class)
        ->callTableAction('pause', $offer);

    expect($offer->fresh()->status)->toBe(OfferStatus::Archived);
});
```

> **warning**
> `OfferStatus` is exactly `Draft | Published | Archived`; there are no
> `STATUS_*` constants and no `active`/`paused` states. `activate`/`pause` map
> to `Published`/`Archived`.

### Applications

```php
use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource;

it('approves a pending application', function (): void {
    $application = AffiliateOfferApplication::factory()->pending()->create();

    livewire(AffiliateOfferApplicationResource\Pages\ListAffiliateOfferApplications::class)
        ->callTableAction('approve', $application);

    expect($application->fresh()->status)->toBe(ApplicationStatus::Approved);
});

it('rejects with a reason', function (): void {
    $application = AffiliateOfferApplication::factory()->pending()->create();

    livewire(AffiliateOfferApplicationResource\Pages\ListAffiliateOfferApplications::class)
        ->callTableAction('reject', $application, ['reason' => 'Traffic mismatch']);

    expect($application->fresh()->rejection_reason)->toBe('Traffic mismatch');
});
```

### Merchant statements (admin-only)

```php
use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\FilamentAffiliateNetwork\Resources\MerchantStatementResource;

it('renders statements for admins', function (): void {
    livewire(MerchantStatementResource\Pages\ListMerchantStatements::class)
        ->assertSuccessful();
});

it('issues a draft statement', function (): void {
    $statement = MerchantStatement::factory()->draft()->create();

    livewire(MerchantStatementResource\Pages\ListMerchantStatements::class)
        ->callTableAction('issue', $statement);

    expect($statement->fresh()->status)->toBe(SettlementStatus::Issued);
});

it('blocks non-admins', function (): void {
    Gate::define('affiliate-network.admin', fn (): bool => false);

    livewire(MerchantStatementResource\Pages\ListMerchantStatements::class)
        ->assertForbidden();
});
```

## Testing relation managers

```php
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\CouponsRelationManager;

use function Pest\Livewire\livewire;

it('assigns a coupon from the offer', function (): void {
    $offer = AffiliateOffer::factory()->published()->create();

    livewire(CouponsRelationManager::class, [
        'ownerRecord' => $offer,
        'pageClass' => \AIarmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages\EditAffiliateOffer::class,
    ])
        ->callTableAction('assign_coupon', data: [
            'affiliate_id' => (string) \Illuminate\Support\Str::uuid(),
            'code' => 'SUMMER10',
        ])
        ->assertHasNoTableActionErrors();

    expect(AffiliateOfferCoupon::where('code', 'SUMMER10')->exists())->toBeTrue();
});
```

Use the same `ownerRecord` + `pageClass` shape for `InvitationsRelationManager`
(`invite`), `DealsRelationManager` (`upsert_deal`), `LegsRelationManager`
(`adjust`, `reverse`, `lock_matured`), `CreativesRelationManager`, and
`LinksRelationManager`.

### Leg actions

```php
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\LegsRelationManager;

it('reverses a posted leg', function (): void {
    $offer = AffiliateOffer::factory()->published()->create();
    $leg = NetworkConversionLeg::factory()->forOffer($offer)->create(['status' => LegStatus::Posted]);

    livewire(LegsRelationManager::class, [
        'ownerRecord' => $offer,
        'pageClass' => \AIarmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages\EditAffiliateOffer::class,
    ])
        ->callTableAction('reverse', $leg, ['reason' => 'refund']);

    expect($leg->fresh()->status)->toBe(LegStatus::Reversed);
});
```

## Testing pages and widgets

`MerchantDashboardPage`, `NetworkStatsWidget`, and `TopOffersWidget` are
network-gated and (for the widgets) network-wide.

```php
use AIArmada\FilamentAffiliateNetwork\Pages\MerchantDashboardPage;
use AIArmada\FilamentAffiliateNetwork\Widgets\NetworkStatsWidget;

it('renders the merchant dashboard', function (): void {
    livewire(MerchantDashboardPage::class)->assertSuccessful();
});

it('renders network stats', function (): void {
    livewire(NetworkStatsWidget::class)->assertSuccessful();
});
```

> **warning**
> `NetworkStatsWidget::getStats()` is `protected`. Assert rendered output or
> `NetworkStatsAggregator::aggregate()` instead of calling it through Livewire.
> `TopOffersWidget` caches ids for 30s via `OwnerCache`; clear
> `OwnerCache::forget(null, 'affiliate-network.top-offer-ids')` between assertions.

## Authorization tests

```php
use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use Illuminate\Support\Facades\Gate;

it('reports admin access from the configured ability', function (): void {
    Gate::define(config('filament-affiliate-network.authorization.admin_ability'), fn (): bool => true);

    expect(NetworkAdminAccess::allows())->toBeTrue();
});
```

## Real Chrome smoke test

`demo/tests/chrome/affiliate-surfaces.mjs` proves the surfaces in real Chrome via
Puppeteer: login, offers table, offer Legs tab + reverse/adjust, site token
rotation, statements list, and postback settings. Deterministic `CHROME-*`
fixtures come from `demo/database/seeders/ChromeSmokeSeeder.php`.

```bash
# From demo/, against a migrated + served app:
npm run chrome:affiliates
BASE_URL=http://127.0.0.1:8000 npm run chrome:affiliates
node tests/chrome/affiliate-surfaces.mjs --no-seed
```

Screenshots land in `demo/tests/chrome/screenshots/` (gitignored). The script
exits non-zero with the failing check names.

## Tips

```php
beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});
```

Wrap multi-tenant tests in `OwnerContext::withOwner($merchant, fn () => ...)`.
Use `MoneyFormatter::formatMinor()` assertions rather than dividing by 100.
