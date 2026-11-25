<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\EntryPoints\Listeners;

use AIArmada\Events\Events\EventRegistrationRefunded;
use AIArmada\Events\Features\Commerce\Actions\RevokePassesForRegistrationAction;

final class RevokePassesOnRegistrationRefunded
{
    public function __construct(
        private readonly RevokePassesForRegistrationAction $revokePasses,
    ) {}

    public function handle(EventRegistrationRefunded $event): void
    {
        $this->revokePasses->handle(
            registrationId: $event->registrationId,
            reason: $event->reason ?? 'Registration refunded',
        );
    }
}
