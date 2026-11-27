<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Rates\Api;

use DateTimeImmutable;

final readonly class RateQuote
{
    public function __construct(
        public string $carrier,
        public string $service,
        public int $rate, // in cents
        public string $currency,
        public int $estimatedDays,
        public ?string $estimatedDeliveryDate = null,
        public ?string $serviceDescription = null,
        public ?array $restrictions = null,
        public bool $calculatedLocally = false,
        public ?string $quoteId = null,
        public ?DateTimeImmutable $expiresAt = null,
        public ?string $note = null,
    ) {}
}
