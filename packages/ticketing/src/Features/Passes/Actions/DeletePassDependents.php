<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Actions;

use AIArmada\Seating\Features\Reservations\Api\ReservationsApi;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\Passes\Models\PassHolder;
use AIArmada\Ticketing\Features\Passes\Models\PassTransfer;

final readonly class DeletePassDependents
{
    public function __construct(private ReservationsApi $reservations) {}

    public function handle(Pass $pass): void
    {
        $this->reservations->releaseAllocations(
            allocToType: $pass->getMorphClass(),
            allocToId: (string) $pass->getKey(),
        );

        PassHolder::query()->where('pass_id', $pass->getKey())->delete();
        PassTransfer::query()->where('pass_id', $pass->getKey())->delete();
    }
}
