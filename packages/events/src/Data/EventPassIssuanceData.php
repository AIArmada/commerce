<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventPassIssuanceData
{
    /**
     * @param  array<int, array<string, mixed>>  $holderAttributes
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $ticketTypeId,
        public int $quantity = 1,
        public array $holderAttributes = [],
        public array $metadata = [],
        public ?string $registrationType = null,
        public ?string $registrationId = null,
        public ?string $occurrenceId = null,
        public ?string $sessionId = null,
    ) {}
}
