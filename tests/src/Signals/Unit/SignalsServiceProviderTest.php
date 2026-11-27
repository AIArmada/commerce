<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Contracts\ReverseGeocoderContract;
use AIArmada\Signals\Contracts\SignalLocationResolverContract;
use AIArmada\Signals\Data\SessionLocation;
use AIArmada\Signals\Drivers\NominatimGeocoder;
use AIArmada\Signals\Features\Alerts\EntryPoints\Console\ProcessSignalAlertsCommand;
use AIArmada\Signals\Features\Ingestion\Actions\SignalLocationResolverPipeline;
use AIArmada\Signals\Features\Ingestion\EntryPoints\Http\Middleware\VerifyTrustedSignalSignature;
use AIArmada\Signals\Features\Ingestion\EntryPoints\Listeners\RecordCommerceSignal;
use AIArmada\Signals\Features\Ingestion\Support\CommerceSignalsIntegrationRegistrar;
use AIArmada\Signals\Features\Ingestion\Support\SignalEventMap;
use AIArmada\Signals\Features\Reporting\EntryPoints\Console\AggregateDailyMetricsCommand;
use AIArmada\Signals\Features\Reporting\Queries\ReportRegistry;
use AIArmada\Signals\SignalsServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use Spatie\LaravelPackageTools\Package;

uses(SignalsTestCase::class);

afterEach(function (): void {
    Mockery::close();
});

it('configures the package name, config, and migrations', function (): void {
    /** @var Package&MockInterface $package */
    $package = Mockery::mock(Package::class);
    $package->shouldReceive('name')->once()->with('signals')->andReturnSelf();
    $package->shouldReceive('hasConfigFile')->once()->withNoArgs()->andReturnSelf();
    $package->shouldReceive('runsMigrations')->once()->withNoArgs()->andReturnSelf();
    $package->shouldReceive('discoversMigrations')->once()->withNoArgs()->andReturnSelf();
    $package->shouldReceive('hasCommand')->once()->with(AggregateDailyMetricsCommand::class)->andReturnSelf();
    $package->shouldReceive('hasCommand')->once()->with(ProcessSignalAlertsCommand::class)->andReturnSelf();

    $provider = new SignalsServiceProvider(app());
    $provider->configurePackage($package);
});

it('registers the default reverse geocoder and optional location resolver on the pipeline', function (): void {
    app()->bind(SignalLocationResolverContract::class, fn (): SignalLocationResolverContract => new class implements SignalLocationResolverContract
    {
        public function resolve(SessionLocation $location): void {}
    });

    $pipeline = app(SignalLocationResolverPipeline::class);

    $geocoders = Closure::bind(fn (): array => $this->geocoders, $pipeline, $pipeline)();
    $resolvers = Closure::bind(fn (): array => $this->resolvers, $pipeline, $pipeline)();

    expect($geocoders)->toHaveCount(1)
        ->and($geocoders[0])->toBeInstanceOf(NominatimGeocoder::class)
        ->and($resolvers)->toHaveCount(1)
        ->and($resolvers[0])->toBeInstanceOf(SignalLocationResolverContract::class);
});

it('registers optional checkout and order listeners', function (): void {
    config()->set('signals.integrations.cart.enabled', true);
    Event::fake();

    app(CommerceSignalsIntegrationRegistrar::class)->boot();

    Event::assertListening('AIArmada\\Affiliates\\Events\\AffiliateAttributed', RecordCommerceSignal::class);
    Event::assertListening('AIArmada\\Affiliates\\Events\\AffiliateConversionRecorded', RecordCommerceSignal::class);
    Event::assertListening('AIArmada\\Cart\\Events\\ItemAdded', RecordCommerceSignal::class);
    Event::assertListening('AIArmada\\Cart\\Events\\ItemRemoved', RecordCommerceSignal::class);
    Event::assertListening('AIArmada\\Cart\\Events\\CartCleared', RecordCommerceSignal::class);
    Event::assertListening('AIArmada\\Checkout\\Events\\CheckoutStarted', RecordCommerceSignal::class);
    Event::assertListening('AIArmada\\Checkout\\Events\\CheckoutCompleted', RecordCommerceSignal::class);
    Event::assertListening('AIArmada\\Orders\\Events\\OrderPaid', RecordCommerceSignal::class);
    Event::assertListening('AIArmada\\Vouchers\\Events\\VoucherApplied', RecordCommerceSignal::class);
    Event::assertListening('AIArmada\\Vouchers\\Events\\VoucherRemoved', RecordCommerceSignal::class);
});

it('keeps one explicit mapping for each commerce signal listener source', function (): void {
    $eventClasses = [
        'AIArmada\\Affiliates\\Events\\AffiliateAttributed',
        'AIArmada\\Affiliates\\Events\\AffiliateConversionRecorded',
        'AIArmada\\Affiliates\\Events\\AffiliateCreated',
        'AIArmada\\Affiliates\\Events\\AffiliateProgramJoined',
        'AIArmada\\Affiliates\\Events\\FraudSignalDetected',
        'AIArmada\\AffiliateNetwork\\Events\\OfferCreated',
        'AIArmada\\AffiliateNetwork\\Events\\OfferUpdated',
        'AIArmada\\AffiliateNetwork\\Events\\ApplicationSubmitted',
        'AIArmada\\AffiliateNetwork\\Events\\ApplicationApproved',
        'AIArmada\\AffiliateNetwork\\Events\\NetworkConversionRecorded',
        'AIArmada\\Cart\\Events\\ItemAdded',
        'AIArmada\\Cart\\Events\\ItemRemoved',
        'AIArmada\\Cart\\Events\\CartCleared',
        'AIArmada\\Cart\\Events\\CartSnapshotSynced',
        'AIArmada\\Cart\\Events\\CartCheckoutStarted',
        'AIArmada\\Cart\\Events\\CartAbandoned',
        'AIArmada\\Cart\\Events\\HighValueCartDetected',
        'AIArmada\\Checkout\\Events\\CheckoutStarted',
        'AIArmada\\Checkout\\Events\\CheckoutCompleted',
        'AIArmada\\Orders\\Events\\OrderPaid',
        'AIArmada\\Orders\\Events\\OrderRefunded',
        'AIArmada\\Vouchers\\Events\\VoucherApplied',
        'AIArmada\\Vouchers\\Events\\VoucherRemoved',
    ];

    $mappings = array_map(static fn (string $eventClass): ?array => SignalEventMap::for($eventClass), $eventClasses);

    expect($mappings)->toHaveCount(23)
        ->and($mappings)->each->not->toBeNull()
        ->and(array_unique(array_map(static fn (array $mapping): string => $mapping['method'], $mappings)))->toHaveCount(23);
});

it('maps affiliate lifecycle events to exact recorder methods and arguments', function (): void {
    expect(SignalEventMap::for('AIArmada\\Affiliates\\Events\\AffiliateCreated'))->toBe([
        'method' => 'recordAffiliateCreated',
        'arguments' => [['property' => 'affiliate', 'type' => 'object']],
    ])->and(SignalEventMap::for('AIArmada\\Affiliates\\Events\\AffiliateProgramJoined'))->toBe([
        'method' => 'recordAffiliateProgramJoined',
        'arguments' => [['property' => 'reference', 'type' => 'object']],
    ])->and(SignalEventMap::for('AIArmada\\Affiliates\\Events\\FraudSignalDetected'))->toBe([
        'method' => 'recordAffiliateFraudSignalDetected',
        'arguments' => [['property' => 'signal', 'type' => 'object']],
    ]);
});

it('resolves the geocoder pipeline through the transport contract', function (): void {
    $geocoder = Mockery::mock(ReverseGeocoderContract::class);
    app()->instance(ReverseGeocoderContract::class, $geocoder);
    app()->forgetInstance(SignalLocationResolverPipeline::class);

    $pipeline = app(SignalLocationResolverPipeline::class);
    $geocoders = Closure::bind(fn (): array => $this->geocoders, $pipeline, $pipeline)();

    expect($geocoders)->toBe([$geocoder]);
});

it('discovers and resolves every built-in report extension', function (): void {
    $registry = app(ReportRegistry::class);

    expect(array_keys($registry->all()))->toEqualCanonicalizing([
        'acquisition',
        'content_performance',
        'conversion_funnel',
        'devices',
        'goals',
        'journey',
        'live_activity',
        'page_view',
        'retention',
    ]);

    foreach ($registry->all() as $type => $report) {
        expect($report->type())->toBe($type)
            ->and($registry->get($type))->toBe($report);
    }
});

it('preserves all signal route names, HTTP methods and middleware', function (): void {
    $prefix = mb_trim((string) config('signals.http.prefix', 'api/signals'), '/');
    $middleware = config('signals.http.middleware', ['api']);
    $routes = [
        'signals.tracker.script' => [(string) config('signals.http.tracker_script', 'tracker.js'), ['GET', 'HEAD'], $middleware],
        'signals.collect.identify' => ['collect/identify', ['POST'], [...$middleware, 'throttle:signals-collect']],
        'signals.collect.browser-event' => ['collect/browser-event', ['POST'], [...$middleware, 'throttle:signals-collect']],
        'signals.collect.pageview' => ['collect/pageview', ['POST'], [...$middleware, 'throttle:signals-collect']],
        'signals.collect.geo' => ['collect/geo', ['POST'], [...$middleware, 'throttle:signals-collect']],
        'signals.collect.server-outcome' => ['collect/server-outcome', ['POST'], [...$middleware, VerifyTrustedSignalSignature::class]],
    ];

    foreach ($routes as $name => [$path, $methods, $expectedMiddleware]) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull()
            ->and($route->uri())->toBe($prefix . '/' . $path)
            ->and($route->methods())->toBe($methods)
            ->and($route->middleware())->toBe($expectedMiddleware);
    }
});
