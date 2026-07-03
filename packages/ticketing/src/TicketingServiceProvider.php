<?php

declare(strict_types=1);

namespace AIArmada\Ticketing;

use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Seating\Actions\ReleaseAllocationsAction;
use AIArmada\Ticketing\Console\Commands\ExpireTransfersCommand;
use AIArmada\Ticketing\Contracts\PassDeliveryServiceInterface;
use AIArmada\Ticketing\Contracts\PassIssuerInterface;
use AIArmada\Ticketing\Contracts\PassTransferServiceInterface;
use AIArmada\Ticketing\Events\PassCancelled;
use AIArmada\Ticketing\Events\PassExpired;
use AIArmada\Ticketing\Events\PassRevoked;
use AIArmada\Ticketing\Events\PassVoided;
use AIArmada\Ticketing\Listeners\IssuePassesOnOrderPaid;
use AIArmada\Ticketing\Listeners\ReleaseSeatsOnPassCancelled;
use AIArmada\Ticketing\Listeners\ReleaseSeatsOnPassExpired;
use AIArmada\Ticketing\Listeners\ReleaseSeatsOnPassRevoked;
use AIArmada\Ticketing\Listeners\ReleaseSeatsOnPassVoided;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Policies\PassTransferPolicy;
use AIArmada\Ticketing\Services\DefaultPassDeliveryService;
use AIArmada\Ticketing\Services\DefaultPassIssuer;
use AIArmada\Ticketing\Services\DefaultPassTransferService;
use AIArmada\Ticketing\Services\NullPassDeliveryService;
use AIArmada\Ticketing\Support\Integration\TicketingIntegration;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class TicketingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('ticketing')
            ->hasConfigFile()
            ->runsMigrations()
            ->hasCommands([
                ExpireTransfersCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(PassIssuerInterface::class, DefaultPassIssuer::class);
        $this->app->singleton(PassDeliveryServiceInterface::class, function ($app) {
            return config('ticketing.notifications.ticket.enabled', true)
                ? $app->make(DefaultPassDeliveryService::class)
                : $app->make(NullPassDeliveryService::class);
        });
        $this->app->bind(PassTransferServiceInterface::class, DefaultPassTransferService::class);
    }

    public function packageBooted(): void
    {
        $this->registerTransferPolicy();
        $this->registerOrderFulfillment();
        $this->registerSeatReleaseListeners();
        $this->registerSchedule();
    }

    private function registerTransferPolicy(): void
    {
        Gate::policy(Pass::class, PassTransferPolicy::class);
    }

    private function registerOrderFulfillment(): void
    {
        if (! config('ticketing.features.auto_issue_passes', true)) {
            return;
        }

        if (TicketingIntegration::ordersAvailable() && class_exists(OrderPaid::class)) {
            Event::listen(OrderPaid::class, IssuePassesOnOrderPaid::class);
        }
    }

    private function registerSeatReleaseListeners(): void
    {
        if (! class_exists(ReleaseAllocationsAction::class)) {
            return;
        }

        Event::listen(PassRevoked::class, ReleaseSeatsOnPassRevoked::class);
        Event::listen(PassCancelled::class, ReleaseSeatsOnPassCancelled::class);
        Event::listen(PassVoided::class, ReleaseSeatsOnPassVoided::class);
        Event::listen(PassExpired::class, ReleaseSeatsOnPassExpired::class);
    }

    private function registerSchedule(): void
    {
        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command(ExpireTransfersCommand::class)->hourly();
        });
    }

    /** @return array<int, string> */
    public function provides(): array
    {
        return [
            PassIssuerInterface::class,
            PassDeliveryServiceInterface::class,
            PassTransferServiceInterface::class,
        ];
    }
}
