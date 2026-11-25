<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\States\RegistrationStatus\Waitlisted;
use Illuminate\Support\Facades\DB;

final class PromoteWaitlistedRegistrationsAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
    ) {}

    public function handle(string $registrationId): void
    {
        DB::transaction(function () use ($registrationId): void {
            /** @var EventRegistration $registration */
            $registration = EventRegistration::query()
                ->whereKey($registrationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($registration->status instanceof Waitlisted) {
                $this->registrationService->approve($registrationId);
            }
        });
    }
}
