<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Queries;

use AIArmada\Ticketing\Features\Passes\Api\PassesApi;
use AIArmada\Ticketing\Features\Passes\Persistence\PassDetacher;

final readonly class EloquentPassesApi implements PassesApi
{
    public function __construct(private PassDetacher $detacher) {}

    public function detachPassesFromTicketType(string $ticketTypeId, ?string $ownerType, string | int | null $ownerId): void
    {
        $this->detacher->detachFromTicketType($ticketTypeId, $ownerType, $ownerId);
    }
}
