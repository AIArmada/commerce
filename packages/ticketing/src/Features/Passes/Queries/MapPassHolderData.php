<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Queries;

use AIArmada\Ticketing\Data\PassHolderData;
use AIArmada\Ticketing\Features\Passes\Models\PassHolder;

final readonly class MapPassHolderData
{
    public function handle(PassHolder $holder): PassHolderData
    {
        return new PassHolderData(
            id: (string) $holder->getKey(),
            holder_type: $holder->holder_type,
            holder_id: $holder->holder_id !== null ? (string) $holder->holder_id : null,
            name: $holder->name,
            email: $holder->email,
        );
    }
}
