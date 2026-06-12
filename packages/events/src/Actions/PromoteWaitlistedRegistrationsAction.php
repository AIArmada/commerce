<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\EventRegistration;

final class PromoteWaitlistedRegistrationsAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
    ) {}

    public function handle(EventRegistration $registration): void
    {
        if ($registration->status === 'waitlisted') {
            $this->registrationService->approve($registration);
        }
    }
}
