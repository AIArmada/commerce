<?php

declare(strict_types=1);

namespace AIArmada\Signals;

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
use AIArmada\Signals\Services\CommerceSignalsRecorder;
use AIArmada\Signals\Services\SignalAlertDispatcher;
use AIArmada\Signals\Services\SignalAlertEvaluator;
use AIArmada\Signals\Services\SignalMetricsAggregator;
use AIArmada\Signals\Services\SignalsDashboardService;
use AIArmada\Signals\Services\TrackedPropertyResolver;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class SignalsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('signals')
            ->hasConfigFile()
            ->discoversMigrations()
            ->hasRoutes(['api'])
            ->hasCommand(AggregateDailyMetricsCommand::class)
            ->hasCommand(ProcessSignalAlertsCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SignalsDashboardService::class);
        $this->app->singleton(SignalMetricsAggregator::class);
        $this->app->singleton(TrackedPropertyResolver::class);
        $this->app->singleton(CommerceSignalsRecorder::class);
        $this->app->singleton(SignalAlertEvaluator::class);
        $this->app->singleton(SignalAlertDispatcher::class);
    }

    public function packageBooted(): void
    {
        $this->bootCartIntegration();
        $this->bootCheckoutIntegration();
        $this->bootOrdersIntegration();
        $this->bootVoucherIntegration();
    }

    private function bootCartIntegration(): void
    {
        if (! config('signals.integrations.cart.enabled', true)) {
            return;
        }

        if (config('signals.integrations.cart.listen_for_item_added', true)) {
            $eventClass = 'AIArmada\\Cart\\Events\\ItemAdded';

            if (class_exists($eventClass)) {
                Event::listen($eventClass, RecordCartItemAddedSignal::class);
            }
        }

        if (config('signals.integrations.cart.listen_for_item_removed', true)) {
            $eventClass = 'AIArmada\\Cart\\Events\\ItemRemoved';

            if (class_exists($eventClass)) {
                Event::listen($eventClass, RecordCartItemRemovedSignal::class);
            }
        }

        if (config('signals.integrations.cart.listen_for_cleared', true)) {
            $eventClass = 'AIArmada\\Cart\\Events\\CartCleared';

            if (class_exists($eventClass)) {
                Event::listen($eventClass, RecordCartClearedSignal::class);
            }
        }
    }

    private function bootCheckoutIntegration(): void
    {
        if (! config('signals.integrations.checkout.enabled', true)) {
            return;
        }

        if (config('signals.integrations.checkout.listen_for_started', true)) {
            $eventClass = 'AIArmada\\Checkout\\Events\\CheckoutStarted';

            if (class_exists($eventClass)) {
                Event::listen($eventClass, RecordCheckoutStartedSignal::class);
            }
        }

        if (! config('signals.integrations.checkout.listen_for_completed', true)) {
            return;
        }

        $eventClass = 'AIArmada\\Checkout\\Events\\CheckoutCompleted';

        if (! class_exists($eventClass)) {
            return;
        }

        Event::listen($eventClass, RecordCheckoutCompletedSignal::class);
    }

    private function bootOrdersIntegration(): void
    {
        if (! config('signals.integrations.orders.enabled', true)) {
            return;
        }

        if (! config('signals.integrations.orders.listen_for_paid', true)) {
            return;
        }

        $eventClass = 'AIArmada\\Orders\\Events\\OrderPaid';

        if (! class_exists($eventClass)) {
            return;
        }

        Event::listen($eventClass, RecordOrderPaidSignal::class);
    }

    private function bootVoucherIntegration(): void
    {
        if (! config('signals.integrations.vouchers.enabled', true)) {
            return;
        }

        if (config('signals.integrations.vouchers.listen_for_applied', true)) {
            $eventClass = 'AIArmada\\Vouchers\\Events\\VoucherApplied';

            if (class_exists($eventClass)) {
                Event::listen($eventClass, RecordVoucherAppliedSignal::class);
            }
        }

        if (config('signals.integrations.vouchers.listen_for_removed', true)) {
            $eventClass = 'AIArmada\\Vouchers\\Events\\VoucherRemoved';

            if (class_exists($eventClass)) {
                Event::listen($eventClass, RecordVoucherRemovedSignal::class);
            }
        }
    }
}
