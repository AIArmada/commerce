<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\States\RegistrationStatus\Waitlisted;

final class PromoteWaitlistedRegistrationsAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
    ) {}

    public function handle(EventRegistration $registration): void
    {
        if ($registration->status instanceof Waitlisted) {
            $this->registrationService->approve($registration);
        }
    }
}
