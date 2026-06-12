<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\SyncEventOrderCompletionAction;
use AIArmada\Events\Events\RegistrationCheckedIn;
use AIArmada\Events\Support\Integration\CommerceIntegration;

final class SyncEventOrderCompletionOnRegistrationCheckedIn
{
    public function handle(RegistrationCheckedIn $event): void
    {
        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            return;
        }

        OwnerContext::withOwner($event->attendance->event->owner ?? null, function () use ($event): void {
            $action = app(SyncEventOrderCompletionAction::class);
            $action->handle($event->attendance);
        });
    }
}
