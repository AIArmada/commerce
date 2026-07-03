<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Listeners;

use AIArmada\Seating\Actions\ReleaseAllocationsAction;
use AIArmada\Ticketing\Events\PassCancelled;

final class ReleaseSeatsOnPassCancelled
{
    public function __construct(
        private readonly ReleaseAllocationsAction $releaseAllocations,
    ) {}

    public function handle(PassCancelled $event): void
    {
        $this->releaseAllocations->handle(
            allocToType: $event->pass->getMorphClass(),
            allocToId: $event->pass->getKey(),
        );
    }
}
