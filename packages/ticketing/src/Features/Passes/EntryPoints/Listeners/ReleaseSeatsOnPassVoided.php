<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\EntryPoints\Listeners;

use AIArmada\Seating\Features\Reservations\Actions\ReleaseAllocationsAction;
use AIArmada\Ticketing\Events\PassVoided;

final class ReleaseSeatsOnPassVoided
{
    public function __construct(
        private readonly ReleaseAllocationsAction $releaseAllocations,
    ) {}

    public function handle(PassVoided $event): void
    {
        $this->releaseAllocations->handle(
            allocToType: $event->pass->getMorphClass(),
            allocToId: $event->pass->getKey(),
        );
    }
}
