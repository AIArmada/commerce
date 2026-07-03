<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\Events\Events\EventRegistrationRefunded;
use AIArmada\Seating\Actions\ReleaseAllocationsAction;

final class ReleaseSeatsOnRegistrationRefunded
{
    public function __construct(
        private readonly ReleaseAllocationsAction $releaseAllocations,
    ) {}

    public function handle(EventRegistrationRefunded $event): void
    {
        $this->releaseAllocations->handle(
            allocToType: $event->registration->getMorphClass(),
            allocToId: $event->registration->getKey(),
        );
    }
}
