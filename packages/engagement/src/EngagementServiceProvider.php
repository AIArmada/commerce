<?php

declare(strict_types=1);

namespace AIArmada\Engagement;

use AIArmada\Engagement\Console\Commands\MatchSubscriptionsCommand;
use AIArmada\Engagement\Console\Commands\ReconcileEngagementCountersCommand;
use AIArmada\Engagement\Console\Commands\SendDueRemindersCommand;
use AIArmada\Engagement\Contracts\EngagementCounterService;
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\EngagementStateResolver;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Contracts\ShareUrlGenerator;
use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\Engagement\Events\BookmarkArchived;
use AIArmada\Engagement\Events\BookmarkCreated;
use AIArmada\Engagement\Events\BookmarkRemoved;
use AIArmada\Engagement\Events\ResponseCancelled;
use AIArmada\Engagement\Events\ResponseChanged;
use AIArmada\Engagement\Events\ResponseCreated;
use AIArmada\Engagement\Integrations\Events\EngagementEventEngagementManager;
use AIArmada\Engagement\Listeners\MatchSubscriptionsOnEventOccurrencePublished;
use AIArmada\Engagement\Services\DefaultEngagementCounterService;
use AIArmada\Engagement\Services\DefaultEngagementManager;
use AIArmada\Engagement\Services\DefaultEngagementPolicyResolver;
use AIArmada\Engagement\Services\DefaultEngagementStateResolver;
use AIArmada\Engagement\Services\DefaultReminderManager;
use AIArmada\Engagement\Services\DefaultShareUrlGenerator;
use AIArmada\Engagement\Services\DefaultSubscriptionManager;
use AIArmada\Events\Contracts\EventEngagementManager;
use AIArmada\Events\Events\EventPublished;
use AIArmada\Events\EventsServiceProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class EngagementServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('engagement')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands([
                MatchSubscriptionsCommand::class,
                ReconcileEngagementCountersCommand::class,
                SendDueRemindersCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton('engagement', fn ($app): EngagementManager => $app->make(EngagementManager::class));

        $this->app->bind(EngagementManager::class, DefaultEngagementManager::class);
        $this->app->bind(SubscriptionManager::class, DefaultSubscriptionManager::class);
        $this->app->bind(ReminderManager::class, DefaultReminderManager::class);
        $this->app->bind(EngagementStateResolver::class, DefaultEngagementStateResolver::class);
        $this->app->bind(EngagementCounterService::class, DefaultEngagementCounterService::class);
        $this->app->bind(EngagementPolicyResolver::class, DefaultEngagementPolicyResolver::class);
        $this->app->bind(ShareUrlGenerator::class, DefaultShareUrlGenerator::class);

        $this->registerEventsIntegration();
        $this->registerEventListeners();
        $this->registerCounterListeners();
    }

    private function registerEventsIntegration(): void
    {
        if (! class_exists(EventsServiceProvider::class)) {
            return;
        }

        if (! interface_exists(EventEngagementManager::class)) {
            return;
        }

        $this->app->bind(
            EventEngagementManager::class,
            EngagementEventEngagementManager::class,
        );
    }

    private function registerEventListeners(): void
    {
        if (! class_exists(EventsServiceProvider::class)) {
            return;
        }

        $dispatcher = $this->app->make(Dispatcher::class);

        if (class_exists(EventPublished::class)) {
            $dispatcher->listen(
                EventPublished::class,
                MatchSubscriptionsOnEventOccurrencePublished::class,
            );
        }
    }

    private function registerCounterListeners(): void
    {
        $service = $this->app->make(EngagementCounterService::class);
        $dispatcher = $this->app->make(Dispatcher::class);

        $dispatcher->listen(BookmarkCreated::class, $service->onBookmarkCreated(...));
        $dispatcher->listen(BookmarkRemoved::class, $service->onBookmarkRemoved(...));
        $dispatcher->listen(BookmarkArchived::class, $service->onBookmarkArchived(...));
        $dispatcher->listen(ResponseCreated::class, $service->onResponseCreated(...));
        $dispatcher->listen(ResponseChanged::class, $service->onResponseChanged(...));
        $dispatcher->listen(ResponseCancelled::class, $service->onResponseCancelled(...));
    }
}
