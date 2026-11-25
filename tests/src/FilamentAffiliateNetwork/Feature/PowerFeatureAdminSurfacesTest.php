<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Contracts\CreatorPostbackTransport;
use AIArmada\AffiliateNetwork\Contracts\DnsResolver;
use AIArmada\AffiliateNetwork\Data\CreatorPostbackResponse;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\PostbackDeliveryStatus;
use AIArmada\AffiliateNetwork\Data\RestrictionContext;
use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Queries\EvaluateActivePromotion;
use AIArmada\AffiliateNetwork\Features\Catalog\Queries\EvaluateOfferRestrictions;
use AIArmada\AffiliateNetwork\Features\Catalog\Queries\OfferMetricsQuery;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\PostConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackDelivery;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Persistence\OwnerCache;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\Pages\CreateAffiliateOffer;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\Pages\EditAffiliateOffer;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\Schemas\AffiliateOfferForm;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\Tables\AffiliateOffersTable;
use AIArmada\FilamentAffiliateNetwork\Features\Dashboard\EntryPoints\Filament\Pages\MerchantDashboardPage;
use AIArmada\FilamentAffiliateNetwork\Features\Dashboard\EntryPoints\Filament\Widgets\NetworkStatsWidget;
use AIArmada\FilamentAffiliateNetwork\Features\Dashboard\EntryPoints\Filament\Widgets\TopOffersWidget;
use AIArmada\FilamentAffiliateNetwork\Features\Dashboard\Queries\NetworkStatsAggregator;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\Pages\CreateCreatorPostback;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\Pages\EditCreatorPostback;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\RelationManagers\DeliveriesRelationManager;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\Schemas\CreatorPostbackForm;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\Tables\CreatorPostbacksTable;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource\RelationManagers\StatementsRelationManager;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource\Tables\AffiliateSitesTable;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Livewire;

class PowerFeatureHostComponent extends Component implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.placeholder');
    }
}

beforeEach(function (): void {
    Gate::define('affiliate-network.admin', fn (): bool => true);
    config(['filament-affiliate-network.authorization.admin_ability' => 'affiliate-network.admin']);
    $this->actingAs(User::factory()->create());
});

function powerFeatureSite(): AffiliateSite
{
    return AffiliateSite::factory()->verified()->create([
        'domain' => 'power-' . uniqid() . '.example.com',
    ]);
}

function powerFeatureOfferSchema(): Schema
{
    return AffiliateOfferForm::configure(
        Schema::make(new PowerFeatureHostComponent)->model(AffiliateOffer::class)->statePath('data')
    );
}

describe('structured offer editors', function (): void {
    test('exposes repeaters, tags inputs, and a searchable fallback select', function (): void {
        $schema = powerFeatureOfferSchema();

        expect($schema->getComponent('event_rates'))->toBeInstanceOf(Repeater::class)
            ->and($schema->getComponent('active_promotions'))->toBeInstanceOf(Repeater::class)
            ->and($schema->getComponent('restrictions.allowed_countries'))->toBeInstanceOf(TagsInput::class)
            ->and($schema->getComponent('restrictions.blocked_devices'))->toBeInstanceOf(Select::class)
            ->and($schema->getComponent('fallback_offer_id'))->toBeInstanceOf(Select::class)
            ->and($schema->getComponent('fallback_offer_id')->isSearchable())->toBeTrue()
            ->and($schema->getComponent('fallback_offer_id')->isMultiple())->toBeFalse();
    });

    test('dehydrates event rate rows into the post-conversion leg map', function (): void {
        $component = powerFeatureOfferSchema()->getComponent('event_rates');

        $dehydrated = $component->getStateToDehydrate([
            ['event_type' => 'purchase', 'rate_base_bp' => '1500', 'rate_fixed_minor' => ''],
            ['event_type' => '', 'rate_base_bp' => '250'],
        ])['data.event_rates'];

        expect($dehydrated)->toBe(['purchase' => ['rate_base_bp' => 1500]]);
    });

    test('dehydrates an already keyed event rate map without losing the event keys', function (): void {
        $component = powerFeatureOfferSchema()->getComponent('event_rates');

        $keyed = ['purchase' => ['rate_base_bp' => 1500], 'signup' => ['rate_fixed_minor' => 500]];

        expect($component->mutateDehydratedState($keyed))->toBe($keyed)
            ->and($component->getStateToDehydrate($keyed)['data.event_rates'])->toBe($keyed);
    });

    test('normalizes blank locking days and keyed event rates', function (): void {
        $nullLocking = AffiliateOfferForm::normalizeData(['locking_days' => null]);
        $emptyLocking = AffiliateOfferForm::normalizeData(['locking_days' => '']);
        $rates = AffiliateOfferForm::normalizeData([
            'event_rates' => [
                ['event_type' => 'purchase', 'rate_base_bp' => '1500', 'rate_fixed_minor' => ''],
            ],
        ]);

        expect($nullLocking['locking_days'])->toBe(0)
            ->and($emptyLocking['locking_days'])->toBe(0)
            ->and($rates['event_rates'])->toBe(['purchase' => ['rate_base_bp' => 1500]]);
    });

    test('dehydrates promotion rows and drops empty entries', function (): void {
        $component = powerFeatureOfferSchema()->getComponent('active_promotions');

        $dehydrated = $component->getStateToDehydrate([
            ['name' => 'Launch', 'bonus_rate_bp' => '500', 'multiplier' => '1.25'],
            ['name' => '', 'bonus_rate_bp' => null, 'multiplier' => null],
        ])['data.active_promotions'];

        expect($dehydrated)->toHaveCount(1)
            ->and($dehydrated[0]['name'])->toBe('Launch')
            ->and($dehydrated[0]['bonus_rate_bp'])->toBe(500)
            ->and($dehydrated[0]['multiplier'])->toEqual(1.25);
    });

    test('normalizes restrictions, casting order bounds and dropping empty groups', function (): void {
        $normalized = AffiliateOfferForm::normalizeData([
            'restrictions' => [
                'allowed_countries' => ['MY'],
                'blocked_devices' => [],
                'min_order_minor' => '250',
                'max_order_minor' => '',
            ],
        ]);

        expect($normalized['restrictions'])->toBe([
            'allowed_countries' => ['MY'],
            'min_order_minor' => 250,
        ]);

        $empty = AffiliateOfferForm::normalizeData([
            'restrictions' => [
                'allowed_countries' => [],
                'min_order_minor' => null,
            ],
        ]);

        expect($empty['restrictions'])->toBeNull();
    });

    test('fallback options exclude the edited offer', function (): void {
        $site = powerFeatureSite();
        $first = AffiliateOffer::factory()->published()->forSite($site)->create();
        $second = AffiliateOffer::factory()->published()->forSite($site)->create();

        $method = new ReflectionMethod(AffiliateOfferForm::class, 'fallbackOfferOptions');
        $method->setAccessible(true);

        $options = $method->invoke(null, $second);

        expect($options)->toHaveKey((string) $first->getKey())
            ->and($options)->not->toHaveKey((string) $second->getKey());
    });

    test('dehydrated event rates drive post-conversion commission end to end', function (): void {
        $site = powerFeatureSite();
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'requires_approval' => false,
            'rate_base_bp' => null,
            'rate_fixed_minor' => null,
            'currency' => 'MYR',
            'event_rates' => ['purchase' => ['rate_base_bp' => 1500]],
        ]);

        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId('power-aff-' . uniqid())->create();

        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) $offer->getKey(),
            affiliateId: (string) $link->affiliate_id,
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'POWER-EVENT-' . uniqid(),
            eventType: 'purchase',
            linkId: (string) $link->getKey(),
        );

        expect($leg->commission_minor)->toBe(1500);
    });

    test('dehydrated promotions feed the active promotion evaluator', function (): void {
        $site = powerFeatureSite();
        $component = powerFeatureOfferSchema()->getComponent('active_promotions');
        $promotions = $component->getStateToDehydrate([
            ['name' => 'Launch', 'override_rate_bp' => '2000'],
        ])['data.active_promotions'];

        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'active_promotions' => $promotions,
        ]);

        $winner = app(EvaluateActivePromotion::class)->evaluate($offer->toRecord(), CarbonImmutable::now());

        expect($winner)->not->toBeNull()
            ->and($winner['name'])->toBe('Launch')
            ->and($winner['override_rate_bp'])->toBe(2000);
    });

    test('normalized restrictions feed the restriction evaluator', function (): void {
        $site = powerFeatureSite();
        $normalized = AffiliateOfferForm::normalizeData([
            'restrictions' => [
                'allowed_countries' => ['MY'],
                'min_order_minor' => '250',
            ],
        ]);

        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'restrictions' => $normalized['restrictions'],
        ]);

        $evaluator = app(EvaluateOfferRestrictions::class);

        expect($evaluator->evaluate($offer->toRecord(), new RestrictionContext(country: 'US')))->toBe('country_restricted')
            ->and($evaluator->evaluate($offer->toRecord(), new RestrictionContext(orderMinor: 100)))->toBe('order_below_minimum')
            ->and($evaluator->evaluate($offer->toRecord(), new RestrictionContext(country: 'MY', orderMinor: 500)))->toBeNull();
    });
});

describe('offer form livewire saves', function (): void {
    test('create page preserves event rate keys and defaults a blank locking period', function (): void {
        $site = powerFeatureSite();

        Livewire::test(CreateAffiliateOffer::class)
            ->fillForm([
                'site_id' => (string) $site->getKey(),
                'name' => 'Live wiring offer',
                'slug' => 'live-wiring-' . uniqid(),
                'status' => OfferStatus::Draft->value,
                'locking_days' => null,
                'event_rates' => [
                    ['event_type' => 'purchase', 'rate_base_bp' => '1500', 'rate_fixed_minor' => ''],
                    ['event_type' => 'signup', 'rate_base_bp' => '', 'rate_fixed_minor' => '500'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $offer = AffiliateOffer::query()->where('name', 'Live wiring offer')->firstOrFail();

        expect($offer->locking_days)->toBe(0)
            ->and($offer->event_rates)->toBe([
                'purchase' => ['rate_base_bp' => 1500],
                'signup' => ['rate_fixed_minor' => 500],
            ]);
    });

    test('edit page rehydrates event rate rows and saves the keyed map', function (): void {
        $site = powerFeatureSite();

        $offer = AffiliateOffer::factory()->forSite($site)->create([
            'locking_days' => 5,
            'event_rates' => [
                'purchase' => ['rate_base_bp' => 1500],
                'signup' => ['rate_fixed_minor' => 500],
            ],
        ]);

        Livewire::test(EditAffiliateOffer::class, ['record' => $offer->getRouteKey()])
            ->assertFormSet([
                'event_rates' => [
                    ['event_type' => 'purchase', 'rate_base_bp' => 1500, 'rate_fixed_minor' => null],
                    ['event_type' => 'signup', 'rate_base_bp' => null, 'rate_fixed_minor' => 500],
                ],
            ])
            ->fillForm([
                'locking_days' => null,
                'event_rates' => [
                    ['event_type' => 'purchase', 'rate_base_bp' => '2000', 'rate_fixed_minor' => ''],
                    ['event_type' => 'signup', 'rate_base_bp' => '', 'rate_fixed_minor' => '750'],
                    ['event_type' => 'upgrade', 'rate_base_bp' => '100', 'rate_fixed_minor' => ''],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($offer->fresh()->locking_days)->toBe(0)
            ->and($offer->fresh()->event_rates)->toBe([
                'purchase' => ['rate_base_bp' => 2000],
                'signup' => ['rate_fixed_minor' => 750],
                'upgrade' => ['rate_base_bp' => 100],
            ]);
    });
});

describe('creator postback resource', function (): void {
    test('gates access on the network admin ability', function (): void {
        expect(CreatorPostbackResource::canViewAny())->toBeTrue()
            ->and(CreatorPostbackResource::shouldRegisterNavigation())->toBeTrue()
            ->and(CreatorPostbackResource::getRelations())->toContain(DeliveriesRelationManager::class);

        config(['filament-affiliate-network.authorization.admin_ability' => '']);

        expect(CreatorPostbackResource::canViewAny())->toBeFalse()
            ->and(CreatorPostbackResource::shouldRegisterNavigation())->toBeFalse();
    });

    test('form exposes endpoint and delivery fields', function (): void {
        $schema = CreatorPostbackForm::configure(
            Schema::make(new PowerFeatureHostComponent)->model(CreatorPostbackEndpoint::class)->statePath('data')
        );

        expect($schema->getComponent('affiliate_id'))->not->toBeNull()
            ->and($schema->getComponent('offer_id'))->toBeInstanceOf(Select::class)
            ->and($schema->getComponent('http_method'))->not->toBeNull()
            ->and($schema->getComponent('events'))->toBeInstanceOf(CheckboxList::class)
            ->and($schema->getComponent('signing_secret'))->not->toBeNull()
            ->and($schema->getComponent('is_active'))->not->toBeNull();
    });

    test('create page delegates to ConfigureCreatorPostback', function (): void {
        $page = (new ReflectionClass(CreateCreatorPostback::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(CreateCreatorPostback::class, 'handleRecordCreation');
        $method->setAccessible(true);

        $endpoint = $method->invoke($page, [
            'affiliate_id' => 'aff-power-1',
            'offer_id' => null,
            'url_template' => 'https://example.com/postbacks?leg={leg_id}&event={event_type}',
            'http_method' => 'POST',
            'events' => ['conversion.recorded'],
            'signing_secret' => 'secret-123',
            'is_active' => true,
        ]);

        expect($endpoint)->toBeInstanceOf(CreatorPostbackEndpoint::class)
            ->and($endpoint->affiliate_id)->toBe('aff-power-1')
            ->and($endpoint->events)->toBe(['conversion.recorded'])
            ->and(decrypt($endpoint->signing_secret_encrypted))->toBe('secret-123');
    });

    test('edit page encrypts a supplied secret and keeps the existing one when blank', function (): void {
        $endpoint = CreatorPostbackEndpoint::factory()->create([
            'signing_secret_encrypted' => encrypt('original'),
        ]);

        $page = (new ReflectionClass(EditCreatorPostback::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(EditCreatorPostback::class, 'handleRecordUpdate');
        $method->setAccessible(true);

        $method->invoke($page, $endpoint, [
            'affiliate_id' => $endpoint->affiliate_id,
            'offer_id' => null,
            'url_template' => 'https://example.com/postbacks?leg={leg_id}',
            'http_method' => 'GET',
            'events' => ['conversion.locked'],
            'signing_secret' => 'rotated',
            'is_active' => true,
        ]);

        expect(decrypt($endpoint->fresh()->signing_secret_encrypted))->toBe('rotated');

        $method->invoke($page, $endpoint->fresh(), [
            'affiliate_id' => $endpoint->affiliate_id,
            'offer_id' => null,
            'url_template' => 'https://example.com/postbacks?leg={leg_id}',
            'http_method' => 'GET',
            'events' => ['conversion.locked'],
            'signing_secret' => '',
            'is_active' => true,
        ]);

        expect(decrypt($endpoint->fresh()->signing_secret_encrypted))->toBe('rotated');
    });

    test('editing an endpoint cannot retarget it to a different affiliate or expose ciphertext', function (): void {
        $endpoint = CreatorPostbackEndpoint::factory()->create(['signing_secret_encrypted' => encrypt('private')]);
        $page = (new ReflectionClass(EditCreatorPostback::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(EditCreatorPostback::class, 'handleRecordUpdate');

        $method->invoke($page, $endpoint, [
            'affiliate_id' => 'different-affiliate',
            'offer_id' => null,
            'url_template' => 'https://example.com/hook',
            'http_method' => 'POST',
            'events' => ['conversion.recorded'],
            'is_active' => true,
        ]);

        expect($endpoint->fresh()->affiliate_id)->toBe($endpoint->affiliate_id)
            ->and($endpoint->fresh()->toArray())->not->toHaveKey('signing_secret_encrypted');
    });

    test('table exposes the endpoint columns, filters, and delete actions', function (): void {
        $table = CreatorPostbacksTable::configure(Table::make(new PowerFeatureHostComponent));

        expect($table->getColumn('affiliate_id'))->not->toBeNull()
            ->and($table->getColumn('offer_id'))->not->toBeNull()
            ->and($table->getColumn('http_method'))->not->toBeNull()
            ->and($table->getColumn('url_template'))->not->toBeNull()
            ->and($table->getColumn('events'))->not->toBeNull()
            ->and($table->getColumn('is_active'))->not->toBeNull()
            ->and($table->getColumn('deliveries_count'))->not->toBeNull()
            ->and($table->getFilter('is_active'))->not->toBeNull()
            ->and($table->getFilter('http_method'))->not->toBeNull()
            ->and($table->getAction('delete'))->not->toBeNull();
    });

    test('delete action removes the endpoint and its deliveries', function (): void {
        $endpoint = CreatorPostbackEndpoint::factory()->create();
        CreatorPostbackDelivery::factory()->forEndpoint($endpoint)->create();

        $host = new PowerFeatureHostComponent;
        $action = CreatorPostbacksTable::configure(Table::make($host))->getAction('delete');
        $action->livewire($host);
        $action->record($endpoint);
        $action->call();

        expect(CreatorPostbackEndpoint::query()->whereKey($endpoint->getKey())->exists())->toBeFalse()
            ->and(CreatorPostbackDelivery::query()->where('endpoint_id', $endpoint->getKey())->exists())->toBeFalse();
    });

    test('bulk delete delegates each record to DeleteCreatorPostback', function (): void {
        $first = CreatorPostbackEndpoint::factory()->create();
        $second = CreatorPostbackEndpoint::factory()->create();

        $host = new PowerFeatureHostComponent;
        $bulk = CreatorPostbacksTable::configure(Table::make($host))->getBulkAction('delete');
        $bulk->livewire($host);
        $bulk->call(['records' => new Collection([$first, $second])]);

        expect(CreatorPostbackEndpoint::query()->whereKey([$first->getKey(), $second->getKey()])->count())->toBe(0);
    });

    test('deliveries relation retries a failed delivery through the transport', function (): void {
        $endpoint = CreatorPostbackEndpoint::factory()->create();
        $delivery = CreatorPostbackDelivery::factory()->forEndpoint($endpoint)->pending()->create();

        app()->instance(CreatorPostbackTransport::class, new class implements CreatorPostbackTransport
        {
            public function send(string $method, string $url, array $headers = [], ?array $body = null): CreatorPostbackResponse
            {
                return new CreatorPostbackResponse(true, 200);
            }
        });

        $host = new PowerFeatureHostComponent;
        $action = (new DeliveriesRelationManager)->table(Table::make($host))->getAction('retry');
        $action->livewire($host);
        $action->record($delivery);
        $action->call();

        expect($delivery->fresh()->status)->toBe(PostbackDeliveryStatus::Delivered)
            ->and($delivery->fresh()->attempts)->toBe(1);
    });
});

describe('affiliate site power actions', function (): void {
    test('resource registers the statements relation manager', function (): void {
        expect(AffiliateSiteResource::getRelations())->toBe([StatementsRelationManager::class]);
    });

    test('table exposes locking and prepaid columns plus the new actions', function (): void {
        $table = AffiliateSitesTable::configure(Table::make(new PowerFeatureHostComponent));

        expect($table->getColumn('default_locking_days'))->not->toBeNull()
            ->and($table->getColumn('prepaid_balance_minor'))->not->toBeNull()
            ->and($table->getAction('verify_strategy'))->not->toBeNull()
            ->and($table->getAction('adjust_prepaid'))->not->toBeNull()
            ->and($table->getAction('rotate_catalog_token'))->not->toBeNull();
    });

    test('verify by strategy verifies a pending site', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'domain' => 'strategy-' . uniqid() . '.example',
            'verification_token' => 'power-token',
        ]);

        app()->instance(DnsResolver::class, new class implements DnsResolver
        {
            public function getTxtRecords(string $hostname): array
            {
                return [['txt' => 'power-token']];
            }
        });

        $host = new PowerFeatureHostComponent;
        $action = AffiliateSitesTable::configure(Table::make($host))->getAction('verify_strategy');
        $action->livewire($host);
        $action->record($site);
        $action->call(['data' => ['method' => 'dns']]);

        expect($site->fresh()->status->value)->toBe('verified');
    });

    test('adjust prepaid balance credits the merchant site', function (): void {
        $site = AffiliateSite::factory()->verified()->create(['prepaid_balance_minor' => 1000]);

        $host = new PowerFeatureHostComponent;
        $action = AffiliateSitesTable::configure(Table::make($host))->getAction('adjust_prepaid');
        $action->livewire($host);
        $action->record($site);
        $action->call(['data' => ['delta_minor' => 2500]]);

        expect($site->fresh()->prepaid_balance_minor)->toBe(3500);
    });

    test('rotate catalog token mints a new stored token', function (): void {
        $site = AffiliateSite::factory()->verified()->create();

        $host = new PowerFeatureHostComponent;
        $action = AffiliateSitesTable::configure(Table::make($host))->getAction('rotate_catalog_token');
        $action->livewire($host);
        $action->record($site);
        $action->call();

        expect($site->fresh()->hasCatalogToken())->toBeTrue()
            ->and($site->fresh()->catalog_token_issued_at)->not->toBeNull();
    });

    test('statements relation manager lists and drives the lifecycle', function (): void {
        $site = powerFeatureSite();
        $draft = MerchantStatement::factory()->forSite($site)->draft()->create();
        $issued = MerchantStatement::factory()->forSite($site)->issued()->create();

        $manager = new StatementsRelationManager;
        $manager->ownerRecord = $site;
        $table = $manager->table(Table::make(new PowerFeatureHostComponent));

        expect($table->getColumn('statement_number'))->not->toBeNull()
            ->and($table->getColumn('payout_minor'))->not->toBeNull()
            ->and($table->getColumn('status'))->not->toBeNull()
            ->and($table->getAction('view'))->not->toBeNull()
            ->and($table->getAction('issue'))->not->toBeNull()
            ->and($table->getAction('mark_paid'))->not->toBeNull()
            ->and($table->getAction('void'))->not->toBeNull();

        $host = new PowerFeatureHostComponent;

        $issue = $table->getAction('issue');
        $issue->livewire($host);
        $issue->record($draft);
        $issue->call();

        $markPaid = $table->getAction('mark_paid');
        $markPaid->livewire($host);
        $markPaid->record($issued);
        $markPaid->call(['data' => ['payment_reference' => 'POWER-REF']]);

        $toVoid = MerchantStatement::factory()->forSite($site)->issued()->create();
        $void = $table->getAction('void');
        $void->livewire($host);
        $void->record($toVoid);
        $void->call();

        expect($draft->fresh()->status)->toBe(SettlementStatus::Issued)
            ->and($issued->fresh()->status)->toBe(SettlementStatus::Paid)
            ->and($issued->fresh()->payment_reference)->toBe('POWER-REF')
            ->and($toVoid->fresh()->status)->toBe(SettlementStatus::Void);
    });
});

describe('offer metrics and network stats', function (): void {
    test('offers table exposes the metric columns', function (): void {
        $table = AffiliateOffersTable::configure(Table::make(new PowerFeatureHostComponent));

        expect($table->getColumn('epc'))->not->toBeNull()
            ->and($table->getColumn('conversion_rate'))->not->toBeNull()
            ->and($table->getColumn('reversal_rate'))->not->toBeNull()
            ->and($table->getColumn('locking_days'))->not->toBeNull();
    });

    test('offer metrics query derives epc and conversion rate from links and legs', function (): void {
        $site = powerFeatureSite();
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create(['currency' => 'MYR']);

        AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId('metric-aff')->create([
            'clicks' => 10,
            'conversions' => 2,
        ]);

        NetworkConversionLeg::factory()->forOffer($offer)->create([
            'status' => LegStatus::Posted,
            'commission_minor' => 500,
            'payout_minor' => 500,
            'fee_minor' => 0,
            'revenue_minor' => 5000,
        ]);

        $metrics = app(OfferMetricsQuery::class)->forOffer($offer->toRecord());

        expect($metrics->clicks)->toBe(10)
            ->and($metrics->conversions)->toBe(1)
            ->and($metrics->conversionRate)->toEqual(0.1)
            ->and($metrics->epcMinor)->toEqual(50.0);
    });

    test('top offers widget exposes epc and conversion rate columns', function (): void {
        $table = (new TopOffersWidget)->table(Table::make(new PowerFeatureHostComponent));

        expect($table->getColumn('epc'))->not->toBeNull()
            ->and($table->getColumn('conversion_rate'))->not->toBeNull();
    });

    test('aggregator counts pending legs and issued statements', function (): void {
        $site = powerFeatureSite();
        $offer = AffiliateOffer::factory()->forSite($site)->create();

        NetworkConversionLeg::factory()->forOffer($offer)->pending()->create();
        MerchantStatement::factory()->forSite($site)->issued()->create();

        $stats = NetworkStatsAggregator::aggregate();

        expect($stats)->toHaveKeys(['pendingLegsCount', 'openStatementsCount'])
            ->and($stats['pendingLegsCount'])->toBeGreaterThanOrEqual(1)
            ->and($stats['openStatementsCount'])->toBeGreaterThanOrEqual(1);
    });

    test('network stats widget surfaces the new cards', function (): void {
        OwnerCache::forget(null, 'affiliate-network.network-stats');

        $widget = new NetworkStatsWidget;
        $method = new ReflectionMethod(NetworkStatsWidget::class, 'getStats');
        $method->setAccessible(true);

        $labels = array_map(
            fn ($stat): string => (string) $stat->getLabel(),
            $method->invoke($widget),
        );

        expect($labels)->toContain('Pending Lock Legs')
            ->and($labels)->toContain('Issued Statements');
    });

    test('merchant dashboard surfaces the new cards', function (): void {
        $labels = array_map(
            fn ($stat): string => (string) $stat->getLabel(),
            app(MerchantDashboardPage::class)->getStats(),
        );

        expect($labels)->toContain('Pending Lock Legs')
            ->and($labels)->toContain('Issued Statements');
    });
});
