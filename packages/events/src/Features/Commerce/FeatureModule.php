<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce;

use AIArmada\Checkout\Contracts\StepContributor;
use AIArmada\CommerceSupport\Events\PaymentRefunded;
use AIArmada\Events\Contracts\EventCheckoutIntentResolver;
use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Contracts\EventPassIssuanceService;
use AIArmada\Events\Events\EventFreeRegistrationConfirmed;
use AIArmada\Events\Events\EventRegistrationCancelled;
use AIArmada\Events\Events\EventRegistrationRefunded;
use AIArmada\Events\Events\RegistrationCheckedIn;
use AIArmada\Events\Features\Commerce\Actions\IssueEventRegistrationPassesAction;
use AIArmada\Events\Features\Commerce\EntryPoints\Checkout\EventsStepContributor;
use AIArmada\Events\Features\Commerce\EntryPoints\Listeners\AllocateEventSeatsOnPassIssued;
use AIArmada\Events\Features\Commerce\EntryPoints\Listeners\CompletePendingRefundOnPaymentRefunded;
use AIArmada\Events\Features\Commerce\EntryPoints\Listeners\IssueEventPassesOnFreeRegistrationConfirmed;
use AIArmada\Events\Features\Commerce\EntryPoints\Listeners\ObserveEventTicketTypePricingConsistency;
use AIArmada\Events\Features\Commerce\EntryPoints\Listeners\ReleaseSeatsOnRegistrationRefunded;
use AIArmada\Events\Features\Commerce\EntryPoints\Listeners\RestoreTicketInventoryOnRegistrationRefunded;
use AIArmada\Events\Features\Commerce\EntryPoints\Listeners\RevokePassesOnRegistrationCancelled;
use AIArmada\Events\Features\Commerce\EntryPoints\Listeners\RevokePassesOnRegistrationRefunded;
use AIArmada\Events\Features\Commerce\EntryPoints\Listeners\SyncEventOrderCompletionOnRegistrationCheckedIn;
use AIArmada\Events\Features\Commerce\EntryPoints\Listeners\SyncEventOrderRegistrationsOnFulfillment;
use AIArmada\Events\Features\Commerce\EntryPoints\Listeners\SyncEventOrderRegistrationsOnOrderCanceled;
use AIArmada\Events\Features\Commerce\EntryPoints\Listeners\SyncEventOrderRegistrationsOnOrderRefunded;
use AIArmada\Events\Features\Commerce\EntryPoints\Listeners\SyncEventOrderRegistrationsOnOrderRefundFailed;
use AIArmada\Events\Features\Commerce\Queries\DefaultEventCheckoutIntentResolver;
use AIArmada\Events\Features\Commerce\Queries\DefaultEventOrderItemFulfillmentResolver;
use AIArmada\Events\Platform\CommerceIntegration;
use AIArmada\Events\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Orders\Events\OrderCanceled;
use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Orders\Events\OrderRefunded;
use AIArmada\Orders\Events\OrderRefundFailed;
use AIArmada\Ticketing\Events\PassIssued;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $this->bindCheckoutIntentResolver($app);
        $this->bindOrderItemFulfillmentResolver($app);

        $app->bind(EventPassIssuanceService::class, IssueEventRegistrationPassesAction::class);
    }

    public function boot(Application $app): void
    {
        $dispatcher = $app->make(Dispatcher::class);

        if (class_exists(PassIssued::class) && config('events.features.auto_allocate_seats', true)) {
            $dispatcher->listen(PassIssued::class, AllocateEventSeatsOnPassIssued::class);
        }

        $dispatcher->listen(EventFreeRegistrationConfirmed::class, IssueEventPassesOnFreeRegistrationConfirmed::class);

        if (config('events.features.auto_revoke_passes_on_cancel', true)) {
            $dispatcher->listen(EventRegistrationCancelled::class, RevokePassesOnRegistrationCancelled::class);
        }

        $dispatcher->listen(EventRegistrationRefunded::class, RevokePassesOnRegistrationRefunded::class);
        $dispatcher->listen(EventRegistrationRefunded::class, ReleaseSeatsOnRegistrationRefunded::class);
        $dispatcher->listen(EventRegistrationRefunded::class, RestoreTicketInventoryOnRegistrationRefunded::class);

        if (CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            $dispatcher->listen(OrderFulfillmentRequired::class, SyncEventOrderRegistrationsOnFulfillment::class);
            $dispatcher->listen(OrderCanceled::class, SyncEventOrderRegistrationsOnOrderCanceled::class);
            $dispatcher->listen(OrderRefunded::class, SyncEventOrderRegistrationsOnOrderRefunded::class);
            $dispatcher->listen(OrderRefundFailed::class, SyncEventOrderRegistrationsOnOrderRefundFailed::class);
            $dispatcher->listen(RegistrationCheckedIn::class, SyncEventOrderCompletionOnRegistrationCheckedIn::class);
            $dispatcher->listen(PaymentRefunded::class, CompletePendingRefundOnPaymentRefunded::class);
        }

        TicketType::observe(ObserveEventTicketTypePricingConsistency::class);

        if (interface_exists(StepContributor::class)) {
            $app->tag(EventsStepContributor::class, 'checkout.steps');
        }
    }

    private function bindCheckoutIntentResolver(Application $app): void
    {
        $configured = config('events.integrations.checkout_intent_resolver');

        if (is_string($configured) && $configured !== '') {
            if (! is_a($configured, EventCheckoutIntentResolver::class, true)) {
                throw new RuntimeException('The events.integrations.checkout_intent_resolver config value must be an EventCheckoutIntentResolver class.');
            }

            $app->bind(EventCheckoutIntentResolver::class, $configured);

            return;
        }

        if (CommerceIntegration::aiArmadaCheckoutAvailable()) {
            $app->bind(EventCheckoutIntentResolver::class, DefaultEventCheckoutIntentResolver::class);
        }
    }

    private function bindOrderItemFulfillmentResolver(Application $app): void
    {
        $configured = config('events.integrations.order_item_fulfillment_resolver');

        if (is_string($configured) && $configured !== '') {
            if (! is_a($configured, EventOrderItemFulfillmentResolver::class, true)) {
                throw new RuntimeException('The events.integrations.order_item_fulfillment_resolver config value must be an EventOrderItemFulfillmentResolver class.');
            }

            $app->bind(EventOrderItemFulfillmentResolver::class, $configured);

            return;
        }

        if (CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            $app->bind(EventOrderItemFulfillmentResolver::class, DefaultEventOrderItemFulfillmentResolver::class);
        }
    }
}
