<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Console\Commands\AggregateDailyMetricsCommand;
use AIArmada\Signals\Console\Commands\ProcessSignalAlertsCommand;
use AIArmada\Signals\Listeners\RecordCartClearedSignal;
use AIArmada\Signals\Listeners\RecordCartItemAddedSignal;
use AIArmada\Signals\Listeners\RecordCartItemRemovedSignal;
use AIArmada\Signals\Listeners\RecordCheckoutCompletedSignal;
use AIArmada\Signals\Listeners\RecordCheckoutStartedSignal;
use AIArmada\Signals\Listeners\RecordOrderPaidSignal;
use AIArmada\Signals\Listeners\RecordVoucherAppliedSignal;
use AIArmada\Signals\Listeners\RecordVoucherRemovedSignal;
use AIArmada\Signals\SignalsServiceProvider;
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
    $package->shouldReceive('discoversMigrations')->once()->withNoArgs()->andReturnSelf();
    $package->shouldReceive('hasRoutes')->once()->with(['api'])->andReturnSelf();
    $package->shouldReceive('hasCommand')->once()->with(AggregateDailyMetricsCommand::class)->andReturnSelf();
    $package->shouldReceive('hasCommand')->once()->with(ProcessSignalAlertsCommand::class)->andReturnSelf();

    $provider = new SignalsServiceProvider(app());
    $provider->configurePackage($package);
});

it('registers the dashboard and aggregator services as singletons', function (): void {
    app()->register(SignalsServiceProvider::class);

    expect(app()->bound(\AIArmada\Signals\Services\SignalsDashboardService::class))->toBeTrue()
        ->and(app()->bound(\AIArmada\Signals\Services\SignalMetricsAggregator::class))->toBeTrue()
        ->and(app()->bound(\AIArmada\Signals\Services\TrackedPropertyResolver::class))->toBeTrue()
        ->and(app()->bound(\AIArmada\Signals\Services\CommerceSignalsRecorder::class))->toBeTrue()
        ->and(app()->bound(\AIArmada\Signals\Services\SignalAlertEvaluator::class))->toBeTrue()
        ->and(app()->bound(\AIArmada\Signals\Services\SignalAlertDispatcher::class))->toBeTrue();
});

it('registers optional checkout and order listeners', function (): void {
    Event::fake();

    Event::assertListening('AIArmada\\Cart\\Events\\ItemAdded', RecordCartItemAddedSignal::class);
    Event::assertListening('AIArmada\\Cart\\Events\\ItemRemoved', RecordCartItemRemovedSignal::class);
    Event::assertListening('AIArmada\\Cart\\Events\\CartCleared', RecordCartClearedSignal::class);
    Event::assertListening('AIArmada\\Checkout\\Events\\CheckoutStarted', RecordCheckoutStartedSignal::class);
    Event::assertListening('AIArmada\\Checkout\\Events\\CheckoutCompleted', RecordCheckoutCompletedSignal::class);
    Event::assertListening('AIArmada\\Orders\\Events\\OrderPaid', RecordOrderPaidSignal::class);
    Event::assertListening('AIArmada\\Vouchers\\Events\\VoucherApplied', RecordVoucherAppliedSignal::class);
    Event::assertListening('AIArmada\\Vouchers\\Events\\VoucherRemoved', RecordVoucherRemovedSignal::class);
});
