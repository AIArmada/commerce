<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\TicketTypes\Api;

interface TicketTypeApi
{
    public function issuanceData(string $ticketTypeId): TicketTypeIssuanceData;

    public function issuanceDataFor(string $ticketableType, string $ticketableId): ?TicketTypeIssuanceData;

    public function isTicketType(string $ticketableType): bool;
}
