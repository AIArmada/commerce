<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Console\Commands\AggregateDailyMetricsCommand;
use AIArmada\Signals\Console\Commands\ProcessSignalAlertsCommand;
use AIArmada\Signals\Contracts\SignalLocationResolverContract;
use AIArmada\Signals\Listeners\RecordAffiliateAttributedSignal;
use AIArmada\Signals\Listeners\RecordAffiliateConversionRecordedSignal;
use AIArmada\Signals\Listeners\RecordCartClearedSignal;
use AIArmada\Signals\Listeners\RecordCartItemAddedSignal;
use AIArmada\Signals\Listeners\RecordCartItemRemovedSignal;
use AIArmada\Signals\Listeners\RecordCheckoutCompletedSignal;
use AIArmada\Signals\Listeners\RecordCheckoutStartedSignal;
use AIArmada\Signals\Listeners\RecordOrderPaidSignal;
use AIArmada\Signals\Listeners\RecordVoucherAppliedSignal;
use AIArmada\Signals\Listeners\RecordVoucherRemovedSignal;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Services\CommerceSignalsRecorder;
use AIArmada\Signals\Services\Geocoders\NominatimGeocoder;
use AIArmada\Signals\Services\SignalAlertDispatcher;
use AIArmada\Signals\Services\SignalAlertEvaluator;
use AIArmada\Signals\Services\SignalLocationResolverPipeline;
use AIArmada\Signals\Services\SignalMetricsAggregator;
use AIArmada\Signals\Services\SignalsDashboardService;
use AIArmada\Signals\Services\TrackedPropertyResolver;
use AIArmada\Signals\SignalsServiceProvider;
use AIArmada\Signals\Support\CommerceSignalsIntegrationRegistrar;
use Illuminate\Support\Facades\Event;
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
    $package->shouldReceive('hasRoutes')->once()->with(['api'])->andReturnSelf();
    $package->shouldReceive('hasCommand')->once()->with(AggregateDailyMetricsCommand::class)->andReturnSelf();
    $package->shouldReceive('hasCommand')->once()->with(ProcessSignalAlertsCommand::class)->andReturnSelf();

    $provider = new SignalsServiceProvider(app());
    $provider->configurePackage($package);
});

it('registers the dashboard and aggregator services as singletons', function (): void {
    app()->register(SignalsServiceProvider::class);

    expect(app()->bound(SignalsDashboardService::class))->toBeTrue()
        ->and(app()->bound(SignalMetricsAggregator::class))->toBeTrue()
        ->and(app()->bound(TrackedPropertyResolver::class))->toBeTrue()
        ->and(app()->bound(CommerceSignalsRecorder::class))->toBeTrue()
        ->and(app()->bound(SignalAlertEvaluator::class))->toBeTrue()
        ->and(app()->bound(SignalAlertDispatcher::class))->toBeTrue()
        ->and(app()->bound(SignalLocationResolverPipeline::class))->toBeTrue();
});

it('registers the default reverse geocoder and optional location resolver on the pipeline', function (): void {
    app()->bind(SignalLocationResolverContract::class, fn (): SignalLocationResolverContract => new class implements SignalLocationResolverContract
    {
        public function resolve(SignalSession $session, array $rawPayload): void {}
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

    Event::assertListening('AIArmada\\Affiliates\\Events\\AffiliateAttributed', RecordAffiliateAttributedSignal::class);
    Event::assertListening('AIArmada\\Affiliates\\Events\\AffiliateConversionRecorded', RecordAffiliateConversionRecordedSignal::class);
    Event::assertListening('AIArmada\\Cart\\Events\\ItemAdded', RecordCartItemAddedSignal::class);
    Event::assertListening('AIArmada\\Cart\\Events\\ItemRemoved', RecordCartItemRemovedSignal::class);
    Event::assertListening('AIArmada\\Cart\\Events\\CartCleared', RecordCartClearedSignal::class);
    Event::assertListening('AIArmada\\Checkout\\Events\\CheckoutStarted', RecordCheckoutStartedSignal::class);
    Event::assertListening('AIArmada\\Checkout\\Events\\CheckoutCompleted', RecordCheckoutCompletedSignal::class);
    Event::assertListening('AIArmada\\Orders\\Events\\OrderPaid', RecordOrderPaidSignal::class);
    Event::assertListening('AIArmada\\Vouchers\\Events\\VoucherApplied', RecordVoucherAppliedSignal::class);
    Event::assertListening('AIArmada\\Vouchers\\Events\\VoucherRemoved', RecordVoucherRemovedSignal::class);
});
