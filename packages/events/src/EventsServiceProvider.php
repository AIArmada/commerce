<?php

declare(strict_types=1);

namespace AIArmada\Events;

use AIArmada\Events\Actions\CreateOccurrenceCartLineAction;
use AIArmada\Events\Actions\CreateRegistrationsForOrderItemAction;
use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Actions\FinalizeOccurredEventOrdersAction;
use AIArmada\Events\Actions\FulfillEventOrderAction;
use AIArmada\Events\Actions\FulfillEventOrderItemAction;
use AIArmada\Events\Actions\RecordEventEngagementAction;
use AIArmada\Events\Actions\StartOccurrenceCheckoutAction;
use AIArmada\Events\Actions\SyncEventOrderCompletionAction;
use AIArmada\Events\Actions\SyncEventOrderRegistrationsAction;
use AIArmada\Events\Console\Commands\FinalizeEventOrdersCommand;
use AIArmada\Events\Contracts\EventAssetResolver;
use AIArmada\Events\Contracts\EventChangeNoticeAudienceResolver;
use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Contracts\EventChangeNoticeWorkflow;
use AIArmada\Events\Contracts\EventCheckoutIntentResolver;
use AIArmada\Events\Contracts\EventClassificationResolver;
use AIArmada\Events\Contracts\EventDisplayTimezoneResolver;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Contracts\EventReferenceResolver;
use AIArmada\Events\Contracts\EventScheduleResolver;
use AIArmada\Events\Contracts\EventSearchEngine;
use AIArmada\Events\Contracts\EventSearchPayloadResolver;
use AIArmada\Events\Events\EventChangeNoticePublished;
use AIArmada\Events\Events\RegistrationCheckedIn;
use AIArmada\Events\Listeners\DispatchEventChangeNoticeNotifications;
use AIArmada\Events\Listeners\SyncEventOrderCompletionOnRegistrationCheckedIn;
use AIArmada\Events\Listeners\SyncEventOrderRegistrationsOnOrderCanceled;
use AIArmada\Events\Listeners\SyncEventOrderRegistrationsOnOrderPaid;
use AIArmada\Events\Listeners\SyncEventOrderRegistrationsOnOrderRefunded;
use AIArmada\Events\Resolvers\DefaultEventAssetResolver;
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
use AIArmada\Events\Services\DefaultEventChangeNoticeWorkflow;
use AIArmada\Events\Services\DefaultEventLifecycleWorkflow;
use AIArmada\Events\Services\DefaultEventModerationWorkflow;
use AIArmada\Events\Services\EloquentEventSearchEngine;
use AIArmada\Events\Services\EventContentSynchronizer;
use AIArmada\Events\Services\EventQueryService;
use AIArmada\Events\Services\RegistrationService;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\Orders\Events\OrderCanceled;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Events\OrderRefunded;
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
        $this->app->singleton(RecordEventEngagementAction::class);
        $this->app->singleton(EventChangeNoticeWorkflow::class, DefaultEventChangeNoticeWorkflow::class);
        $this->app->singleton(EventModerationWorkflow::class, DefaultEventModerationWorkflow::class);
        $this->app->singleton(EventLifecycleWorkflow::class, DefaultEventLifecycleWorkflow::class);
        $this->app->singleton(EventContentSynchronizer::class);
        $this->app->singleton(EventQueryService::class);
        $this->app->bind(EventDisplayTimezoneResolver::class, $this->displayTimezoneResolverClass());
        $this->app->bind(EventClassificationResolver::class, $this->classificationResolverClass());
        $this->app->bind(EventAssetResolver::class, $this->assetResolverClass());
        $this->app->bind(EventCheckoutIntentResolver::class, $this->checkoutIntentResolverClass());
        $this->app->bind(EventChangeNoticeAudienceResolver::class, $this->changeNoticeAudienceResolverClass());
        $this->app->bind(EventChangeNoticeNotificationDispatcher::class, $this->changeNoticeNotificationDispatcherClass());
        $this->app->bind(EventReferenceResolver::class, $this->referenceResolverClass());
        $this->app->bind(EventScheduleResolver::class, $this->scheduleResolverClass());
        $this->app->bind(EventSearchEngine::class, $this->searchEngineClass());
        $this->app->bind(EventSearchPayloadResolver::class, $this->searchPayloadResolverClass());

        $this->app->make(Dispatcher::class)
            ->listen(EventChangeNoticePublished::class, DispatchEventChangeNoticeNotifications::class);

        if (CommerceIntegration::aiArmadaCheckoutAvailable()) {
            $this->app->singleton(CreateOccurrenceCartLineAction::class);
            $this->app->singleton(StartOccurrenceCheckoutAction::class);
        }

        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            return;
        }

        $this->app->singleton(CreateRegistrationsForOrderItemAction::class);
        $this->app->singleton(FinalizeOccurredEventOrdersAction::class);
        $this->app->bind(EventOrderItemFulfillmentResolver::class, $this->fulfillmentResolverClass());
        $this->app->bind(FulfillEventOrderAction::class);
        $this->app->bind(FulfillEventOrderItemAction::class);
        $this->app->singleton(SyncEventOrderRegistrationsAction::class);
        $this->app->singleton(SyncEventOrderCompletionAction::class);

        $dispatcher = $this->app->make(Dispatcher::class);

        $dispatcher->listen(OrderPaid::class, SyncEventOrderRegistrationsOnOrderPaid::class);
        $dispatcher->listen(OrderCanceled::class, SyncEventOrderRegistrationsOnOrderCanceled::class);
        $dispatcher->listen(OrderRefunded::class, SyncEventOrderRegistrationsOnOrderRefunded::class);
        $dispatcher->listen(RegistrationCheckedIn::class, SyncEventOrderCompletionOnRegistrationCheckedIn::class);
    }

    /**
     * @return class-string<EventOrderItemFulfillmentResolver>
     */
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

    /**
     * @return class-string<EventClassificationResolver>
     */
    private function classificationResolverClass(): string
    {
        $resolver = config('events.classifications.resolver');

        if ($resolver === null) {
            return DefaultEventClassificationResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventClassificationResolver::class, true)) {
            /** @var class-string<EventClassificationResolver> $resolver */
            return $resolver;
        }

        throw new RuntimeException(
            'The events.classifications.resolver config value must be an EventClassificationResolver class.',
        );
    }

    /**
     * @return class-string<EventAssetResolver>
     */
    private function assetResolverClass(): string
    {
        $resolver = config('events.assets.resolver');

        if ($resolver === null) {
            return DefaultEventAssetResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventAssetResolver::class, true)) {
            /** @var class-string<EventAssetResolver> $resolver */
            return $resolver;
        }

        throw new RuntimeException(
            'The events.assets.resolver config value must be an EventAssetResolver class.',
        );
    }

    /**
     * @return class-string<EventReferenceResolver>
     */
    private function referenceResolverClass(): string
    {
        $resolver = config('events.references.resolver');

        if ($resolver === null) {
            return DefaultEventReferenceResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventReferenceResolver::class, true)) {
            /** @var class-string<EventReferenceResolver> $resolver */
            return $resolver;
        }

        throw new RuntimeException(
            'The events.references.resolver config value must be an EventReferenceResolver class.',
        );
    }

    /**
     * @return class-string<EventScheduleResolver>
     */
    private function scheduleResolverClass(): string
    {
        $resolver = config('events.schedule.resolver');

        if ($resolver === null) {
            return NullEventScheduleResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventScheduleResolver::class, true)) {
            /** @var class-string<EventScheduleResolver> $resolver */
            return $resolver;
        }

        throw new RuntimeException(
            'The events.schedule.resolver config value must be an EventScheduleResolver class.',
        );
    }

    /**
     * @return class-string<EventSearchEngine>
     */
    private function searchEngineClass(): string
    {
        $resolver = config('events.search.engine');

        if ($resolver === null) {
            return EloquentEventSearchEngine::class;
        }

        if (is_string($resolver) && is_a($resolver, EventSearchEngine::class, true)) {
            /** @var class-string<EventSearchEngine> $resolver */
            return $resolver;
        }

        throw new RuntimeException(
            'The events.search.engine config value must be an EventSearchEngine class.',
        );
    }

    /**
     * @return class-string<EventCheckoutIntentResolver>
     */
    private function checkoutIntentResolverClass(): string
    {
        $resolver = config('events.integrations.checkout_intent_resolver');

        if ($resolver === null) {
            if (! CommerceIntegration::aiArmadaCheckoutAvailable()) {
                return NullEventCheckoutIntentResolver::class;
            }

            return DefaultEventCheckoutIntentResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventCheckoutIntentResolver::class, true)) {
            /** @var class-string<EventCheckoutIntentResolver> $resolver */
            return $resolver;
        }

        throw new RuntimeException(
            'The events.integrations.checkout_intent_resolver config value must be an EventCheckoutIntentResolver class.',
        );
    }

    /**
     * @return class-string<EventChangeNoticeAudienceResolver>
     */
    private function changeNoticeAudienceResolverClass(): string
    {
        $resolver = config('events.change_notices.audience_resolver');

        if ($resolver === null) {
            return DefaultEventChangeNoticeAudienceResolver::class;
        }

        if (is_string($resolver) && is_a($resolver, EventChangeNoticeAudienceResolver::class, true)) {
            /** @var class-string<EventChangeNoticeAudienceResolver> $resolver */
            return $resolver;
        }

        throw new RuntimeException(
            'The events.change_notices.audience_resolver config value must be an EventChangeNoticeAudienceResolver class.',
        );
    }

    /**
     * @return class-string<EventChangeNoticeNotificationDispatcher>
     */
    private function changeNoticeNotificationDispatcherClass(): string
    {
        $dispatcher = config('events.change_notices.notification_dispatcher');

        if ($dispatcher === null) {
            return NullEventChangeNoticeNotificationDispatcher::class;
        }

        if (is_string($dispatcher) && is_a($dispatcher, EventChangeNoticeNotificationDispatcher::class, true)) {
            /** @var class-string<EventChangeNoticeNotificationDispatcher> $dispatcher */
            return $dispatcher;
        }

        throw new RuntimeException(
            'The events.change_notices.notification_dispatcher config value must be an EventChangeNoticeNotificationDispatcher class.',
        );
    }
}
