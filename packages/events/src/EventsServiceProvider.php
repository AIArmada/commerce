<?php

declare(strict_types=1);

namespace AIArmada\Events;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\StepContributor;
use AIArmada\Engagement\EngagementServiceProvider;
use AIArmada\Engagement\Integrations\Events\EngagementEventEngagementManager;
use AIArmada\Events\Actions\AutoAddRequiredTicketBundlesAction;
use AIArmada\Events\Actions\ExpandTicketTypeComponentsAction;
use AIArmada\Events\Actions\IssueEventRegistrationPassesAction;
use AIArmada\Events\Actions\PromoteInterestedToConfirmedAction;
use AIArmada\Events\Actions\RecordAgentTicketSaleAction;
use AIArmada\Events\Actions\RecordHeadcountLogAction;
use AIArmada\Events\Actions\RecordWalkInAction;
use AIArmada\Events\Actions\RegisterForFreeAction;
use AIArmada\Events\Actions\SyncManagementAssignmentToAuthzAction;
use AIArmada\Events\Checkout\EventsStepContributor;
use AIArmada\Events\Contracts\EventChangeNoticeAudienceResolver;
use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Contracts\EventChangeNoticeWorkflow;
use AIArmada\Events\Contracts\EventCheckInService;
use AIArmada\Events\Contracts\EventCheckoutIntentResolver;
use AIArmada\Events\Contracts\EventClassificationResolver;
use AIArmada\Events\Contracts\EventCloneService;
use AIArmada\Events\Contracts\EventDisplayTimezoneResolver;
use AIArmada\Events\Contracts\EventEngagementManager;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Contracts\EventReferenceResolver;
use AIArmada\Events\Contracts\EventRegistrationEligibility;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\EventScheduleResolver;
use AIArmada\Events\Contracts\EventSearchEngine;
use AIArmada\Events\Contracts\EventSearchIndexer;
use AIArmada\Events\Contracts\EventSearchPayloadResolver;
use AIArmada\Events\Contracts\EventTaxonomyHierarchy;
use AIArmada\Events\Contracts\EventTemplateService;
use AIArmada\Events\Contracts\EventTranslationProvider;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Events\EventChangeNoticePublished;
use AIArmada\Events\Events\EventFreeRegistrationConfirmed;
use AIArmada\Events\Events\EventRegistrationApproved;
use AIArmada\Events\Events\EventRegistrationCancelled;
use AIArmada\Events\Events\EventRegistrationRefunded;
use AIArmada\Events\Events\RegistrationCheckedIn;
use AIArmada\Events\Integrations\NullEventEngagementManager;
use AIArmada\Events\Listeners\AllocateEventSeatsOnPassIssued;
use AIArmada\Events\Listeners\CancelBundleChildrenOnParentCanceled;
use AIArmada\Events\Listeners\DispatchEventChangeNoticeNotifications;
use AIArmada\Events\Listeners\IssueEventPassesOnFreeRegistrationConfirmed;
use AIArmada\Events\Listeners\ObserveEventTicketTypePricingConsistency;
use AIArmada\Events\Listeners\ReleaseSeatsOnRegistrationRefunded;
use AIArmada\Events\Listeners\RevokePassesOnRegistrationCancelled;
use AIArmada\Events\Listeners\RevokePassesOnRegistrationRefunded;
use AIArmada\Events\Listeners\SyncEventOrderCompletionOnRegistrationCheckedIn;
use AIArmada\Events\Listeners\SyncEventOrderRegistrationsOnOrderCanceled;
use AIArmada\Events\Listeners\SyncEventOrderRegistrationsOnOrderPaid;
use AIArmada\Events\Listeners\SyncEventOrderRegistrationsOnOrderRefunded;
use AIArmada\Events\Models\EventApprovalRequest;
use AIArmada\Events\Models\EventAttribute;
use AIArmada\Events\Models\EventAudience;
use AIArmada\Events\Models\EventAvailabilityBlock;
use AIArmada\Events\Models\EventClassification;
use AIArmada\Events\Models\EventManagementAssignment;
use AIArmada\Events\Models\EventManagementAssignmentRequest;
use AIArmada\Events\Models\EventModerationAction;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRecurrenceRule;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventReport;
use AIArmada\Events\Models\EventRevision;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventSubmission;
use AIArmada\Events\Models\EventVerification;
use AIArmada\Events\Notifications\EventWelcomeNotification;
use AIArmada\Events\Observers\EventAttributeObserver;
use AIArmada\Events\Observers\EventAudienceObserver;
use AIArmada\Events\Observers\EventClassificationObserver;
use AIArmada\Events\Observers\EventObserver;
use AIArmada\Events\Observers\EventOccurrenceObserver;
use AIArmada\Events\Observers\EventSessionObserver;
use AIArmada\Events\Policies\EventPolicy;
use AIArmada\Events\Resolvers\DefaultEventChangeNoticeAudienceResolver;
use AIArmada\Events\Resolvers\DefaultEventCheckoutIntentResolver;
use AIArmada\Events\Resolvers\DefaultEventClassificationResolver;
use AIArmada\Events\Resolvers\DefaultEventDisplayTimezoneResolver;
use AIArmada\Events\Resolvers\DefaultEventOrderItemFulfillmentResolver;
use AIArmada\Events\Resolvers\DefaultEventReferenceResolver;
use AIArmada\Events\Resolvers\DefaultEventRegistrationEligibility;
use AIArmada\Events\Resolvers\DefaultEventRegistrationScopeResolver;
use AIArmada\Events\Resolvers\DefaultEventSearchPayloadResolver;
use AIArmada\Events\Resolvers\NullEventCheckoutIntentResolver;
use AIArmada\Events\Resolvers\NullEventOrderItemFulfillmentResolver;
use AIArmada\Events\Resolvers\NullEventScheduleResolver;
use AIArmada\Events\Resolvers\NullEventTranslationProvider;
use AIArmada\Events\Services\DefaultEventChangeNoticeWorkflow;
use AIArmada\Events\Services\DefaultEventCheckInService;
use AIArmada\Events\Services\DefaultEventLifecycleWorkflow;
use AIArmada\Events\Services\DefaultEventModerationWorkflow;
use AIArmada\Events\Services\EloquentEventSearchEngine;
use AIArmada\Events\Services\EventCloneServiceImpl;
use AIArmada\Events\Services\EventNotificationDispatcher;
use AIArmada\Events\Services\EventQueryService;
use AIArmada\Events\Services\EventSearchDocumentBuilder;
use AIArmada\Events\Services\EventTaxonomyHierarchyService;
use AIArmada\Events\Services\EventTemplateServiceImpl;
use AIArmada\Events\Services\RegistrationService;
use AIArmada\Events\Support\EventOwnerScope;
use AIArmada\Events\Support\EventSubmissionOwnerScope;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\Events\Support\ModelResolver;
use AIArmada\Events\Support\PolymorphicOwnerScope;
use AIArmada\FilamentAuthz\FilamentAuthzServiceProvider;
use AIArmada\Orders\Events\OrderCanceled;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Events\OrderRefunded;
use AIArmada\Ticketing\Events\PassIssued;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
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
        $eventClass = ModelResolver::eventClass();
        Gate::policy($eventClass, EventPolicy::class);

        $this->app->singleton(EventQueryService::class);

        $this->app->singleton(RegistrationService::class);
        $this->app->singleton(EventChangeNoticeWorkflow::class, DefaultEventChangeNoticeWorkflow::class);
        $this->app->singleton(EventModerationWorkflow::class, DefaultEventModerationWorkflow::class);
        $this->app->singleton(EventLifecycleWorkflow::class, DefaultEventLifecycleWorkflow::class);

        $this->app->bind(EventCheckInService::class, DefaultEventCheckInService::class);
        $this->app->bind(RegistrationServiceInterface::class, RegistrationService::class);

        $this->app->singleton(EventCloneService::class, EventCloneServiceImpl::class);
        $this->app->singleton(EventTemplateService::class, EventTemplateServiceImpl::class);

        $this->app->bind(EventEngagementManager::class, NullEventEngagementManager::class);

        if (class_exists(EngagementServiceProvider::class)) {
            $this->app->bind(
                EventEngagementManager::class,
                EngagementEventEngagementManager::class,
            );
        }

        $this->app->bind(EventDisplayTimezoneResolver::class, $this->displayTimezoneResolverClass());
        $this->app->bind(EventClassificationResolver::class, $this->classificationResolverClass());
        $this->app->singleton(EventTaxonomyHierarchy::class, EventTaxonomyHierarchyService::class);
        $this->app->bind(EventReferenceResolver::class, $this->referenceResolverClass());
        $this->app->bind(EventScheduleResolver::class, $this->scheduleResolverClass());
        $this->app->bind(EventSearchEngine::class, $this->searchEngineClass());
        $this->app->bind(EventSearchPayloadResolver::class, $this->searchPayloadResolverClass());
        $this->app->bind(EventChangeNoticeAudienceResolver::class, $this->changeNoticeAudienceResolverClass());
        $this->app->bind(EventChangeNoticeNotificationDispatcher::class, $this->changeNoticeNotificationDispatcherClass());

        $indexerClass = $this->searchIndexerClass();
        $this->app->bind(EventSearchIndexer::class, fn (): EventSearchIndexer => app($indexerClass));
        $this->app->bind(EventTranslationProvider::class, NullEventTranslationProvider::class);

        $this->app->bind(EventRegistrationScopeResolver::class, DefaultEventRegistrationScopeResolver::class);
        $this->app->bind(EventRegistrationEligibility::class, DefaultEventRegistrationEligibility::class);

        $this->app->singleton(RegisterForFreeAction::class);
        $this->app->singleton(PromoteInterestedToConfirmedAction::class);
        $this->app->singleton(RecordWalkInAction::class);
        $this->app->singleton(RecordHeadcountLogAction::class);
        $this->app->singleton(AutoAddRequiredTicketBundlesAction::class);
        $this->app->singleton(ExpandTicketTypeComponentsAction::class);
        $this->app->singleton(IssueEventRegistrationPassesAction::class);
        $this->app->singleton(RecordAgentTicketSaleAction::class);

        $this->app->make(Dispatcher::class)
            ->listen(EventChangeNoticePublished::class, DispatchEventChangeNoticeNotifications::class);

        $this->app->make(Dispatcher::class)
            ->listen(EventFreeRegistrationConfirmed::class, IssueEventPassesOnFreeRegistrationConfirmed::class);

        if (class_exists(PassIssued::class) && config('events.features.auto_allocate_seats', true)) {
            $this->app->make(Dispatcher::class)
                ->listen(PassIssued::class, AllocateEventSeatsOnPassIssued::class);
        }

        $this->app->make(Dispatcher::class)
            ->listen(EventRegistrationCancelled::class, CancelBundleChildrenOnParentCanceled::class);

        $dispatcher = $this->app->make(Dispatcher::class);

        if (config('events.features.auto_revoke_passes_on_cancel', true)) {
            $dispatcher->listen(EventRegistrationCancelled::class, RevokePassesOnRegistrationCancelled::class);
        }

        $dispatcher->listen(EventRegistrationRefunded::class, RevokePassesOnRegistrationRefunded::class);
        $dispatcher->listen(EventRegistrationRefunded::class, ReleaseSeatsOnRegistrationRefunded::class);

        if (CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            $this->app->bind(EventOrderItemFulfillmentResolver::class, $this->fulfillmentResolverClass());
            $this->app->bind(EventCheckoutIntentResolver::class, $this->checkoutIntentResolverClass());

            $dispatcher = $this->app->make(Dispatcher::class);
            $dispatcher->listen(OrderPaid::class, SyncEventOrderRegistrationsOnOrderPaid::class);
            $dispatcher->listen(OrderCanceled::class, SyncEventOrderRegistrationsOnOrderCanceled::class);
            $dispatcher->listen(OrderRefunded::class, SyncEventOrderRegistrationsOnOrderRefunded::class);
            $dispatcher->listen(RegistrationCheckedIn::class, SyncEventOrderCompletionOnRegistrationCheckedIn::class);
        }

        $this->app->make(Dispatcher::class)->listen(EventRegistrationApproved::class, function (EventRegistrationApproved $event): void {
            if (! (bool) config('events.notifications.welcome.enabled', true)) {
                return;
            }

            $notification = new EventWelcomeNotification($event->registration);
            $recipient = $event->registration->routeNotificationForMail($notification);

            if ($recipient === null) {
                return;
            }

            Notification::route('mail', $recipient)->notify($notification);
        });

        if (class_exists(FilamentAuthzServiceProvider::class)) {
            $this->app->make(Dispatcher::class)
                ->listen(
                    'eloquent.created: ' . EventManagementAssignment::class,
                    SyncManagementAssignmentToAuthzAction::class,
                );
        }

        $this->registerMorphMap();
    }

    public function bootingPackage(): void
    {
        $eventClass = ModelResolver::eventClass();

        EventSubmissionOwnerScope::register();
        EventOwnerScope::registerDefaults();
        PolymorphicOwnerScope::register(EventApprovalRequest::class, 'approvable');
        PolymorphicOwnerScope::register(EventAvailabilityBlock::class, 'blockable', 'event_id');
        PolymorphicOwnerScope::register(EventManagementAssignment::class, 'manageable', 'event_id');
        PolymorphicOwnerScope::register(EventManagementAssignmentRequest::class, 'manageable');
        PolymorphicOwnerScope::register(EventModerationAction::class, 'actionable', 'event_id');
        PolymorphicOwnerScope::register(EventRecurrenceRule::class, 'recurrenceTarget', 'event_id');
        PolymorphicOwnerScope::register(EventReport::class, 'reportable', 'event_id');
        PolymorphicOwnerScope::register(EventRevision::class, 'revisable', 'event_id');
        PolymorphicOwnerScope::register(EventVerification::class, 'verifiable', 'event_id');

        if (config('events.sync.build_search_documents')) {
            $eventClass::observe(EventObserver::class);
            EventOccurrence::observe(EventOccurrenceObserver::class);
            EventSession::observe(EventSessionObserver::class);
        }

        if (config('events.sync.build_search_documents')) {
            EventAttribute::observe(EventAttributeObserver::class);
            EventAudience::observe(EventAudienceObserver::class);
        }

        if (config('events.sync.classifications_to_facets')) {
            EventClassification::observe(EventClassificationObserver::class);
        }

        TicketType::observe(ObserveEventTicketTypePricingConsistency::class);

        if (interface_exists(StepContributor::class)) {
            $this->app->tag(EventsStepContributor::class, 'checkout.steps');
        }
    }

    private function registerMorphMap(): void
    {
        Relation::morphMap([
            'event_registration' => EventRegistration::class,
            'event_submission' => EventSubmission::class,
        ]);
    }

    private function checkoutPipelineAvailable(): bool
    {
        return interface_exists(CartManagerInterface::class)
            && interface_exists(CheckoutServiceInterface::class);
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

    private function searchIndexerClass(): string
    {
        $resolver = config('events.search.indexer');

        if ($resolver === null) {
            return EventSearchDocumentBuilder::class;
        }

        if (is_string($resolver) && is_a($resolver, EventSearchIndexer::class, true)) {
            return $resolver;
        }

        throw new RuntimeException('The events.search.indexer config value must be an EventSearchIndexer class.');
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
            return EventNotificationDispatcher::class;
        }

        if (is_string($dispatcher) && is_a($dispatcher, EventChangeNoticeNotificationDispatcher::class, true)) {
            return $dispatcher;
        }

        throw new RuntimeException('The events.change_notices.notification_dispatcher config value must be an EventChangeNoticeNotificationDispatcher class.');
    }
}
