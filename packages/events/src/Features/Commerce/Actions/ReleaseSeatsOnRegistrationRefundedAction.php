<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Contracts\EventSeatingDriver;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;

final class ReleaseSeatsOnRegistrationRefundedAction
{
    public function __construct(
        private readonly EventSeatingDriver $seating,
        private readonly RegistrationsApi $registrations,
    ) {}

    public function handle(string $registrationId): int
    {
        return $this->seating->releaseAllocations(
            allocatedToType: $this->registrations->registrationMorphClass(),
            allocatedToId: $registrationId,
        );
    }
}
