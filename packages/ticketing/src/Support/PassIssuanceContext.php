<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Support;

use AIArmada\Ticketing\Models\TicketType;
use Carbon\CarbonImmutable;

final class PassIssuanceContext
{
    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $holderAttributes
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly TicketType $ticketType,
        public readonly int $quantity = 1,
        public readonly array $holderAttributes = [],
        public readonly array $metadata = [],
        public readonly ?string $registrationType = null,
        public readonly ?string $registrationId = null,
        public readonly ?string $occurrenceId = null,
        public readonly ?string $sessionId = null,
        public readonly CarbonImmutable $issuedAt = new CarbonImmutable,
    ) {}
}
