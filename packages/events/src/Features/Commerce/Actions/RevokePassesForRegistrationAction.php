<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Contracts\EventTicketingDriver;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;

final class RevokePassesForRegistrationAction
{
    public function __construct(
        private readonly EventTicketingDriver $ticketing,
        private readonly RegistrationsApi $registrations,
    ) {}

    /**
     * @return int Number of passes revoked
     */
    public function handle(string $registrationId, ?string $reason = null): int
    {
        return $this->ticketing->revokePasses(
            registrationType: $this->registrations->registrationMorphClass(),
            registrationId: $registrationId,
            reason: $reason ?? 'Registration terminal',
        );
    }
}
