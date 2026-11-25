<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\EntryPoints\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Events\RegistrationCheckedIn;
use AIArmada\Events\Features\Commerce\Actions\SyncEventOrderCompletionAction;
use AIArmada\Events\Platform\CommerceIntegration;
use AIArmada\Events\Platform\EventOwnerResolver;

final class SyncEventOrderCompletionOnRegistrationCheckedIn
{
    public function __construct(
        private readonly SyncEventOrderCompletionAction $syncCompletion,
    ) {}

    public function handle(RegistrationCheckedIn $event): void
    {
        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            return;
        }

        OwnerContext::withOwner(EventOwnerResolver::resolve($event->attendance->eventId), function () use ($event): void {
            $this->syncCompletion->handle($event->attendance->id);
        });
    }
}
