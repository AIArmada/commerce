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
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource;

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
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource;

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
use AIArmada\FilamentAffiliateNetwork\Features\Enrollment\EntryPoints\Filament\Resources\AffiliateOfferApplicationResource;

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
use AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource;

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
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\RelationManagers\CouponsRelationManager;

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
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\RelationManagers\LegsRelationManager;

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

## Testing structured offer editors

```php
use AIArmada\AffiliateNetwork\Features\Catalog\Queries\EvaluateActivePromotion;
use AIArmada\AffiliateNetwork\Features\Catalog\Queries\EvaluateOfferRestrictions;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\Schemas\AffiliateOfferForm;
use Filament\Schemas\Schema;

it('hydrates and dehydrates event rates, promotions, and restrictions', function (): void {
    $schema = AffiliateOfferForm::configure(
        Schema::make($host)->model(AffiliateOffer::class)->statePath('data')
    );

    $schema->fill([
        'event_rates' => [
            'purchase' => ['rate_base_bp' => 1500, 'rate_fixed_minor' => 500],
        ],
        'active_promotions' => [
            ['name' => 'Launch', 'bonus_rate_bp' => 500],
            ['name' => '', 'multiplier' => null],
        ],
        'restrictions' => [
            'allowed_countries' => ['MY'],
            'blocked_devices' => ['tablet'],
            'min_order_minor' => '250',
        ],
    ]);

    $state = $schema->getState();

    expect($state['event_rates']['purchase']['rate_base_bp'])->toBe(1500)
        ->and($state['active_promotions'])->toHaveCount(1)
        ->and($state['restrictions']['min_order_minor'])->toBe(250);
});
```

The dehydrated shapes feed `PostConversionLeg` (`event_rates`),
`EvaluateActivePromotion` (`active_promotions`), and
`EvaluateOfferRestrictions` (`restrictions`) unchanged. `fallback_offer_id` is a
searchable select of published offers that excludes the record being edited.

## Testing creator postbacks

```php
use AIArmada\AffiliateNetwork\Contracts\CreatorPostbackTransport;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackDelivery;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\RelationManagers\DeliveriesRelationManager;

it('renders the postbacks list for admins', function (): void {
    livewire(CreatorPostbackResource\Pages\ListCreatorPostbacks::class)->assertSuccessful();
});

it('retries a failed delivery through the relation manager', function (): void {
    $endpoint = CreatorPostbackEndpoint::factory()->create();
    $delivery = CreatorPostbackDelivery::factory()->forEndpoint($endpoint)->failed()->create();

    app()->instance(CreatorPostbackTransport::class, new FakeSuccessfulTransport);

    livewire(DeliveriesRelationManager::class, [
        'ownerRecord' => $endpoint,
        'pageClass' => CreatorPostbackResource\Pages\EditCreatorPostback::class,
    ])->callTableAction('retry', $delivery);

    expect($delivery->fresh()->status)->toBe(PostbackDeliveryStatus::Delivered);
});
```

## Testing site power actions

```php
it('adjusts prepaid balance from the table action', function (): void {
    $site = AffiliateSite::factory()->verified()->create(['prepaid_balance_minor' => 1000]);

    livewire(AffiliateSiteResource\Pages\ListAffiliateSites::class)
        ->callTableAction('adjust_prepaid', $site, ['delta_minor' => 2500]);

    expect($site->fresh()->prepaid_balance_minor)->toBe(3500);
});

it('rotates the catalog token', function (): void {
    $site = AffiliateSite::factory()->verified()->create();

    livewire(AffiliateSiteResource\Pages\ListAffiliateSites::class)
        ->callTableAction('rotate_catalog_token', $site);

    expect($site->fresh()->hasCatalogToken())->toBeTrue();
});
```

`StatementsRelationManager` mirrors the statement lifecycle (View, Issue, Mark
Paid, Void) and can be tested with the `ownerRecord` + `pageClass` pattern.

## Testing metrics and stats

```php
use AIArmada\FilamentAffiliateNetwork\Features\Dashboard\Queries\NetworkStatsAggregator;

it('exposes offer metrics columns', function (): void {
    $table = AffiliateOffersTable::configure(Table::make($host));

    expect($table->getColumn('epc'))->not->toBeNull()
        ->and($table->getColumn('conversion_rate'))->not->toBeNull()
        ->and($table->getColumn('reversal_rate'))->not->toBeNull()
        ->and($table->getColumn('locking_days'))->not->toBeNull();
});

it('aggregates pending legs and issued statements', function (): void {
    $stats = NetworkStatsAggregator::aggregate();

    expect($stats)->toHaveKeys(['pendingLegsCount', 'openStatementsCount']);
});
```

## Testing pages and widgets

`MerchantDashboardPage`, `NetworkStatsWidget`, and `TopOffersWidget` are
network-gated and (for the widgets) network-wide.

```php
use AIArmada\FilamentAffiliateNetwork\Features\Dashboard\EntryPoints\Filament\Pages\MerchantDashboardPage;
use AIArmada\FilamentAffiliateNetwork\Features\Dashboard\EntryPoints\Filament\Widgets\NetworkStatsWidget;

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
use AIArmada\FilamentAffiliateNetwork\Platform\NetworkAdminAccess;
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
