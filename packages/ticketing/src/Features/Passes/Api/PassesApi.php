<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Api;

interface PassesApi
{
    public function detachPassesFromTicketType(string $ticketTypeId, ?string $ownerType, string | int | null $ownerId): void;
}
