<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\TicketTypes\Api;

use Carbon\CarbonImmutable;

final readonly class TicketTypeIssuanceData
{
    public function __construct(
        public string $id,
        public string $ticketableType,
        public string $ticketableId,
        public ?CarbonImmutable $transferWindowEndsAt = null,
    ) {}
}
