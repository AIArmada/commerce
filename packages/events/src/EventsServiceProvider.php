<?php

declare(strict_types=1);

namespace AIArmada\Events;

use AIArmada\Engagement\EngagementServiceProvider;
use AIArmada\Engagement\Integrations\Events\EngagementEventEngagementManager;
use AIArmada\Events\Actions\SyncManagementAssignmentToAuthzAction;
use AIArmada\Events\Contracts\EventChangeNoticeAudienceResolver;
use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Contracts\EventChangeNoticeWorkflow;
use AIArmada\Events\Contracts\EventCheckInService;
use AIArmada\Events\Contracts\EventCheckoutIntentResolver;
use AIArmada\Events\Contracts\EventClassificationResolver;
use AIArmada\Events\Contracts\EventDisplayTimezoneResolver;
use AIArmada\Events\Contracts\EventEngagementManager;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Contracts\EventPassIssuer;
use AIArmada\Events\Contracts\EventReferenceResolver;
use AIArmada\Events\Contracts\EventScheduleResolver;
use AIArmada\Events\Contracts\EventSearchEngine;
use AIArmada\Events\Contracts\EventSearchIndexer;
use AIArmada\Events\Contracts\EventSearchPayloadResolver;
use AIArmada\Events\Contracts\EventSeatAllocator;
use AIArmada\Events\Contracts\EventTranslationProvider;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Events\EventChangeNoticePublished;
use AIArmada\Events\Events\RegistrationCheckedIn;
use AIArmada\Events\Integrations\NullEventEngagementManager;
use AIArmada\Events\Listeners\DispatchEventChangeNoticeNotifications;
use AIArmada\Events\Listeners\SyncEventOrderCompletionOnRegistrationCheckedIn;
use AIArmada\Events\Listeners\SyncEventOrderRegistrationsOnOrderCanceled;
use AIArmada\Events\Listeners\SyncEventOrderRegistrationsOnOrderPaid;
use AIArmada\Events\Listeners\SyncEventOrderRegistrationsOnOrderRefunded;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventManagementAssignment;
use AIArmada\Events\Policies\EventPolicy;
use AIArmada\Events\Resolvers\DefaultEventChangeNoticeAudienceResolver;
use AIArmada\Events\Resolvers\DefaultEventCheckoutIntentResolver;
use AIArmada\Events\Resolvers\DefaultEventClassificationResolver;
use AIArmada\Events\Resolvers\DefaultEventDisplayTimezoneResolver;
use AIArmada\Events\Resolvers\DefaultEventOrderItemFulfillmentResolver;
use AIArmada\Events\Resolvers\DefaultEventReferenceResolver;
use AIArmada\Events\Resolvers\DefaultEventSearchPayloadResolver;
use AIArmada\Events\Resolvers\NullEventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Resolvers\NullEventCheckoutIntentResolver;
use AIArmada\Events\Resolvers\NullEventOrderItemFulfillmentResolver;
use AIArmada\Events\Resolvers\NullEventScheduleResolver;
use AIArmada\Events\Resolvers\NullEventSearchIndexer;
use AIArmada\Events\Resolvers\NullEventTranslationProvider;
use AIArmada\Events\Services\DefaultEventChangeNoticeWorkflow;
use AIArmada\Events\Services\DefaultEventCheckInService;
use AIArmada\Events\Services\DefaultEventLifecycleWorkflow;
use AIArmada\Events\Services\DefaultEventModerationWorkflow;
use AIArmada\Events\Services\DefaultEventPassIssuer;
use AIArmada\Events\Services\DefaultEventSeatAllocator;
use AIArmada\Events\Services\EloquentEventSearchEngine;
use AIArmada\Events\Services\EventQueryService;
use AIArmada\Events\Services\RegistrationService;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\FilamentAuthz\FilamentAuthzServiceProvider;
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Orders\Events\OrderCanceled;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Events\OrderRefunded;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class EventsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('events')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function registeringPackage(): void
    {
        Gate::policy(Event::class, EventPolicy::class);

        $this->app->singleton(EventQueryService::class);
        $this->app->singleton(RegistrationService::class);
        $this->app->singleton(EventChangeNoticeWorkflow::class, DefaultEventChangeNoticeWorkflow::class);
        $this->app->singleton(EventModerationWorkflow::class, DefaultEventModerationWorkflow::class);
        $this->app->singleton(EventLifecycleWorkflow::class, DefaultEventLifecycleWorkflow::class);

        $this->app->bind(EventCheckInService::class, DefaultEventCheckInService::class);
        $this->app->bind(EventPassIssuer::class, DefaultEventPassIssuer::class);
        $this->app->bind(EventSeatAllocator::class, DefaultEventSeatAllocator::class);
        $this->app->bind(RegistrationServiceInterface::class, RegistrationService::class);

        $this->app->bind(EventEngagementManager::class, NullEventEngagementManager::class);

        if (class_exists(EngagementServiceProvider::class)) {
            $this->app->bind(
                EventEngagementManager::class,
                EngagementEventEngagementManager::class,
            );
        }

        $this->app->bind(EventDisplayTimezoneResolver::class, $this->displayTimezoneResolverClass());
        $this->app->bind(EventClassificationResolver::class, $this->classificationResolverClass());
        $this->app->bind(EventReferenceResolver::class, $this->referenceResolverClass());
        $this->app->bind(EventScheduleResolver::class, $this->scheduleResolverClass());
        $this->app->bind(EventSearchEngine::class, $this->searchEngineClass());
        $this->app->bind(EventSearchPayloadResolver::class, $this->searchPayloadResolverClass());
        $this->app->bind(EventChangeNoticeAudienceResolver::class, $this->changeNoticeAudienceResolverClass());
        $this->app->bind(EventChangeNoticeNotificationDispatcher::class, $this->changeNoticeNotificationDispatcherClass());

        $this->app->bind(EventSearchIndexer::class, NullEventSearchIndexer::class);
        $this->app->bind(EventTranslationProvider::class, NullEventTranslationProvider::class);

        $this->app->make(Dispatcher::class)
            ->listen(EventChangeNoticePublished::class, DispatchEventChangeNoticeNotifications::class);

        if (CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            $this->app->bind(EventOrderItemFulfillmentResolver::class, $this->fulfillmentResolverClass());
            $this->app->bind(EventCheckoutIntentResolver::class, $this->checkoutIntentResolverClass());

            $dispatcher = $this->app->make(Dispatcher::class);
            $dispatcher->listen(OrderPaid::class, SyncEventOrderRegistrationsOnOrderPaid::class);
            $dispatcher->listen(OrderCanceled::class, SyncEventOrderRegistrationsOnOrderCanceled::class);
            $dispatcher->listen(OrderRefunded::class, SyncEventOrderRegistrationsOnOrderRefunded::class);
            $dispatcher->listen(RegistrationCheckedIn::class, SyncEventOrderCompletionOnRegistrationCheckedIn::class);
        }

        if (class_exists(FilamentAuthzServiceProvider::class)) {
            $this->app->make(Dispatcher::class)
                ->listen(
                    'eloquent.created: ' . EventManagementAssignment::class,
                    SyncManagementAssignmentToAuthzAction::class,
                );
        }
    }

    public function bootingPackage(): void
    {
        if (! interface_exists(CheckoutStepRegistryInterface::class)) {
            return;
        }

        $registry = $this->app->make(CheckoutStepRegistryInterface::class);

        if (! $registry->isEnabled('create_order')) {
            return;
        }

        $step = new \AIArmada\Events\Steps\CreateEventRegistrationsStep(
            createRegistrations: $this->app->make(\AIArmada\Events\Actions\CreateRegistrationsForOrderItemAction::class),
        );

        if ($registry->has('create_event_registrations')) {
            $registry->replace('create_event_registrations', $step);

            return;
        }

        $registry->insertAfter('create_order', 'create_event_registrations', $step);
    }

    private function checkoutPipelineAvailable(): bool
    {
        return interface_exists(\AIArmada\Cart\Contracts\CartManagerInterface::class)
            && interface_exists(\AIArmada\Checkout\Contracts\CheckoutServiceInterface::class);
    }

    private function fulfillmentResolverClass(): string
    {
        $resolver = config('events.integrations.order_item_fulfillment_resolver');

        if ($resolver === null) {
            if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
                return NullEventOrderItemFulfillmentResolver::class;
            }

            return DefaultEventOrderItemFulfillmentResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventOrderItemFulfillmentResolver::class, true)) {
            return $resolver;
        }

        throw new RuntimeException('The events.integrations.order_item_fulfillment_resolver config value must be an EventOrderItemFulfillmentResolver class.');
    }

    private function checkoutIntentResolverClass(): string
    {
        $resolver = config('events.integrations.checkout_intent_resolver');

        if ($resolver === null) {
            if (! $this->checkoutPipelineAvailable()) {
                return NullEventCheckoutIntentResolver::class;
            }

            return DefaultEventCheckoutIntentResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventCheckoutIntentResolver::class, true)) {
            return $resolver;
        }

        throw new RuntimeException('The events.integrations.checkout_intent_resolver config value must be an EventCheckoutIntentResolver class.');
    }

    private function displayTimezoneResolverClass(): string
    {
        $resolver = config('events.timezone.display_timezone_resolver');

        if ($resolver === null) {
            return DefaultEventDisplayTimezoneResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventDisplayTimezoneResolver::class, true)) {
            return $resolver;
        }

        throw new RuntimeException('The events.timezone.display_timezone_resolver config value must be an EventDisplayTimezoneResolver class.');
    }

    private function searchPayloadResolverClass(): string
    {
        $resolver = config('events.search.payload_resolver');

        if ($resolver === null) {
            return DefaultEventSearchPayloadResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventSearchPayloadResolver::class, true)) {
            return $resolver;
        }

        throw new RuntimeException('The events.search.payload_resolver config value must be an EventSearchPayloadResolver class.');
    }

    private function classificationResolverClass(): string
    {
        $resolver = config('events.classifications.resolver');

        if ($resolver === null) {
            return DefaultEventClassificationResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventClassificationResolver::class, true)) {
            return $resolver;
        }

        throw new RuntimeException('The events.classifications.resolver config value must be an EventClassificationResolver class.');
    }

    private function referenceResolverClass(): string
    {
        $resolver = config('events.references.resolver');

        if ($resolver === null) {
            return DefaultEventReferenceResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventReferenceResolver::class, true)) {
            return $resolver;
        }

        throw new RuntimeException('The events.references.resolver config value must be an EventReferenceResolver class.');
    }

    private function scheduleResolverClass(): string
    {
        $resolver = config('events.schedule.resolver');

        if ($resolver === null) {
            return NullEventScheduleResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventScheduleResolver::class, true)) {
            return $resolver;
        }

        throw new RuntimeException('The events.schedule.resolver config value must be an EventScheduleResolver class.');
    }

    private function searchEngineClass(): string
    {
        $resolver = config('events.search.engine');

        if ($resolver === null) {
            return EloquentEventSearchEngine::class;
        }

        if (is_string($resolver) && is_a($resolver, EventSearchEngine::class, true)) {
            return $resolver;
        }

        throw new RuntimeException('The events.search.engine config value must be an EventSearchEngine class.');
    }

    private function changeNoticeAudienceResolverClass(): string
    {
        $resolver = config('events.change_notices.audience_resolver');

        if ($resolver === null) {
            return DefaultEventChangeNoticeAudienceResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventChangeNoticeAudienceResolver::class, true)) {
            return $resolver;
        }

        throw new RuntimeException('The events.change_notices.audience_resolver config value must be an EventChangeNoticeAudienceResolver class.');
    }

    private function changeNoticeNotificationDispatcherClass(): string
    {
        $dispatcher = config('events.change_notices.notification_dispatcher');

        if ($dispatcher === null) {
            return NullEventChangeNoticeNotificationDispatcher::class;
        }

        if (is_string($dispatcher) && is_a($dispatcher, EventChangeNoticeNotificationDispatcher::class, true)) {
            return $dispatcher;
        }

        throw new RuntimeException('The events.change_notices.notification_dispatcher config value must be an EventChangeNoticeNotificationDispatcher class.');
    }
}
