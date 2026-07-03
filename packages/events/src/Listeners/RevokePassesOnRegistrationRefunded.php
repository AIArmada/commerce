<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\Events\Actions\RevokePassesForRegistrationAction;
use AIArmada\Events\Events\EventRegistrationRefunded;

final class RevokePassesOnRegistrationRefunded
{
    public function __construct(
        private readonly RevokePassesForRegistrationAction $revokePasses,
    ) {}

    public function handle(EventRegistrationRefunded $event): void
    {
        $this->revokePasses->handle(
            registration: $event->registration,
            reason: $event->reason ?? 'Registration refunded',
        );
    }
}
