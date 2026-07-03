<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\Events\Actions\RevokePassesForRegistrationAction;
use AIArmada\Events\Events\EventRegistrationCancelled;

final class RevokePassesOnRegistrationCancelled
{
    public function __construct(
        private readonly RevokePassesForRegistrationAction $revokePasses,
    ) {}

    public function handle(EventRegistrationCancelled $event): void
    {
        $this->revokePasses->handle(
            registration: $event->registration,
            reason: $event->reason ?? 'Registration cancelled',
        );
    }
}
