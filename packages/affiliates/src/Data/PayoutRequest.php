<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

final readonly class PayoutRequest
{
    /** @param array<string, mixed> $methodDetails */
    public function __construct(
        public string $id,
        public int $totalMinor,
        public string $currency,
        public string $status,
        public ?string $operationId = null,
        public ?string $operationStatus = null,
        public ?string $externalReference = null,
        public bool $hasAffiliate = false,
        public array $methodDetails = [],
    ) {}
}
