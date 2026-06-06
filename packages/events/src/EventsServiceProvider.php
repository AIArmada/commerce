<?php

declare(strict_types=1);

namespace AIArmada\Events;

use AIArmada\Events\Actions\CreateRegistrationsForOrderItemAction;
use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Actions\FinalizeOccurredEventOrdersAction;
use AIArmada\Events\Actions\FulfillEventOrderAction;
use AIArmada\Events\Actions\FulfillEventOrderItemAction;
use AIArmada\Events\Actions\SyncEventOrderCompletionAction;
use AIArmada\Events\Console\Commands\FinalizeEventOrdersCommand;
use AIArmada\Events\Contracts\EventDisplayTimezoneResolver;
use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Contracts\EventSearchPayloadResolver;
use AIArmada\Events\Events\RegistrationCheckedIn;
use AIArmada\Events\Listeners\SyncEventOrderCompletionOnRegistrationCheckedIn;
use AIArmada\Events\Resolvers\DefaultEventDisplayTimezoneResolver;
use AIArmada\Events\Resolvers\DefaultEventSearchPayloadResolver;
use AIArmada\Events\Resolvers\NullEventOrderItemFulfillmentResolver;
use AIArmada\Events\Services\RegistrationService;
use AIArmada\Events\Support\CommerceIntegration;
use Illuminate\Contracts\Events\Dispatcher;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class EventsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('events')
            ->hasConfigFile('events')
            ->runsMigrations()
            ->discoversMigrations();

        if (CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            $package->hasCommand(FinalizeEventOrdersCommand::class);
        }
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(EnsureOccurrenceAction::class);
        $this->app->singleton(RegistrationService::class);
        $this->app->bind(EventDisplayTimezoneResolver::class, $this->displayTimezoneResolverClass());
        $this->app->bind(EventSearchPayloadResolver::class, $this->searchPayloadResolverClass());

        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            return;
        }

        $this->app->singleton(CreateRegistrationsForOrderItemAction::class);
        $this->app->singleton(FinalizeOccurredEventOrdersAction::class);
        $this->app->bind(EventOrderItemFulfillmentResolver::class, $this->fulfillmentResolverClass());
        $this->app->bind(FulfillEventOrderAction::class);
        $this->app->bind(FulfillEventOrderItemAction::class);
        $this->app->singleton(SyncEventOrderCompletionAction::class);

        $this->app->make(Dispatcher::class)
            ->listen(RegistrationCheckedIn::class, SyncEventOrderCompletionOnRegistrationCheckedIn::class);
    }

    /**
     * @return class-string<EventOrderItemFulfillmentResolver>
     */
    private function fulfillmentResolverClass(): string
    {
        $resolver = config('events.integrations.order_item_fulfillment_resolver');

        if ($resolver === null) {
            return NullEventOrderItemFulfillmentResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventOrderItemFulfillmentResolver::class, true)) {
            /** @var class-string<EventOrderItemFulfillmentResolver> $resolver */
            return $resolver;
        }

        throw new RuntimeException(
            'The events.integrations.order_item_fulfillment_resolver config value must be an EventOrderItemFulfillmentResolver class.',
        );
    }

    /**
     * @return class-string<EventDisplayTimezoneResolver>
     */
    private function displayTimezoneResolverClass(): string
    {
        $resolver = config('events.timezone.display_timezone_resolver');

        if ($resolver === null) {
            return DefaultEventDisplayTimezoneResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventDisplayTimezoneResolver::class, true)) {
            /** @var class-string<EventDisplayTimezoneResolver> $resolver */
            return $resolver;
        }

        throw new RuntimeException(
            'The events.timezone.display_timezone_resolver config value must be an EventDisplayTimezoneResolver class.',
        );
    }

    /**
     * @return class-string<EventSearchPayloadResolver>
     */
    private function searchPayloadResolverClass(): string
    {
        $resolver = config('events.search.payload_resolver');

        if ($resolver === null) {
            return DefaultEventSearchPayloadResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventSearchPayloadResolver::class, true)) {
            /** @var class-string<EventSearchPayloadResolver> $resolver */
            return $resolver;
        }

        throw new RuntimeException(
            'The events.search.payload_resolver config value must be an EventSearchPayloadResolver class.',
        );
    }
}
