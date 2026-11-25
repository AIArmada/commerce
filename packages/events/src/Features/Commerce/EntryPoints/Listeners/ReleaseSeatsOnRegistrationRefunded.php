<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\EntryPoints\Listeners;

use AIArmada\Events\Events\EventRegistrationRefunded;
use AIArmada\Events\Features\Commerce\Actions\ReleaseSeatsOnRegistrationRefundedAction;

final class ReleaseSeatsOnRegistrationRefunded
{
    public function __construct(
        private readonly ReleaseSeatsOnRegistrationRefundedAction $releaseSeats,
    ) {}

    public function handle(EventRegistrationRefunded $event): void
    {
        $this->releaseSeats->handle($event->registrationId);
    }
}
