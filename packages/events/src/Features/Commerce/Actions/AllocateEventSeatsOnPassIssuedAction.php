<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Contracts\EventSeatingDriver;

final class AllocateEventSeatsOnPassIssuedAction
{
    public function __construct(
        private readonly EventSeatingDriver $seating,
    ) {}

    public function handle(string $passId): void
    {
        $this->seating->allocatePassSeats($passId);
    }
}
