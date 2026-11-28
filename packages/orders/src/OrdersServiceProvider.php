<?php

declare(strict_types=1);

namespace AIArmada\Orders;

use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Orders\Features\Outbox\EntryPoints\Commands\RelayOrderOutboxCommand;
use AIArmada\Orders\Features\Outbox\EntryPoints\Commands\SweepOrderOutboxCommand;
use AIArmada\Orders\Notifications\PaymentConfirmationNotification;
use AIArmada\Orders\Platform\ExtensionRegistry;
use AIArmada\Orders\Platform\FeatureCatalog;
use AIArmada\Orders\Platform\FeatureModule;
use AIArmada\Orders\Support\OrderHandlerRegistrar;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class OrdersServiceProvider extends PackageServiceProvider
{
    /** @var list<FeatureModule> */
    private array $modules = [];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('orders')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasCommands([
                RelayOrderOutboxCommand::class,
                SweepOrderOutboxCommand::class,
            ])
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn ($app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $feature) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $feature . '\\FeatureModule');

            if (! $module instanceof FeatureModule) {
                throw new InvalidArgumentException('Invalid orders feature module: ' . $feature);
            }

            $module->register($this->app);
            $this->modules[] = $module;
        }

        $this->app->singleton(OrderHandlerRegistrar::class);
        $this->registerEventListeners();
        $this->registerTransitionListeners();
    }

    public function bootingPackage(): void
    {
        $this->registerPolicies();
    }

    public function packageBooted(): void
    {
        foreach ($this->modules as $module) {
            $module->boot($this->app);
        }
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Models\Order::class, Policies\OrderPolicy::class);
        Gate::policy(Models\OrderItem::class, Policies\OrderItemPolicy::class);
        Gate::policy(Models\OrderPayment::class, Policies\OrderPaymentPolicy::class);
        Gate::policy(Models\OrderRefund::class, Policies\OrderRefundPolicy::class);
        Gate::policy(Models\OrderNote::class, Policies\OrderNotePolicy::class);

        if (class_exists(\Spatie\Health\Facades\Health::class)) {
            \Spatie\Health\Facades\Health::checks([
                Health\OrderProcessingCheck::new(),
            ]);
        }
    }

    protected function registerEventListeners(): void
    {
        $dispatcher = $this->app->make(Dispatcher::class);

        if (config('orders.integrations.docs.enabled', false)) {
            if (interface_exists(DocServiceInterface::class)) {
                $dispatcher->listen(Events\OrderPaid::class, EntryPoints\Listeners\CreateInvoiceForPaidOrder::class);
            }
        }

        if ((bool) config('orders.notifications.payment_confirmation.enabled', true)) {
            $dispatcher->listen(Events\OrderPaid::class, function (Events\OrderPaid $event): void {
                $notification = new PaymentConfirmationNotification(
                    $event->order,
                    $event->transactionId,
                    $event->gateway,
                );

                $recipient = $event->order->routeNotificationForMail($notification);

                if ($recipient === null) {
                    return;
                }

                Notification::route('mail', $recipient)->notify($notification);
            });
        }
    }

    protected function registerTransitionListeners(): void
    {
        $dispatcher = $this->app->make(Dispatcher::class);

        if (config('orders.integrations.inventory.enabled', true)) {
            $dispatcher->listen(Events\OrderProcessingStarted::class, EntryPoints\Listeners\DeductInventoryOnPaymentConfirmed::class);
            $dispatcher->listen(Events\OrderCancelInitiated::class, EntryPoints\Listeners\ReleaseInventoryOnOrderCanceled::class);
        }

        // Registered unconditionally: the affiliates toggle is evaluated at
        // handle time, preserving the previous inline-call semantics.
        $dispatcher->listen(Events\OrderFulfillmentRequired::class, EntryPoints\Listeners\AttributeCommissionOnFulfillment::class);
    }
}
