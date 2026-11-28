<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Data;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class PassIssuanceContext
{
    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $holderAttributes
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
        public CarbonImmutable $issuedAt = new CarbonImmutable,
        public ?string $ownerType = null,
        public ?string $ownerId = null,
    ) {
        if (($this->ownerType === null) !== ($this->ownerId === null)) {
            throw new InvalidArgumentException('Owner type and owner id must both be present or both be null.');
        }
    }
}
