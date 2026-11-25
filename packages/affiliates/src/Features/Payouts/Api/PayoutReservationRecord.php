<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

final readonly class PayoutReservationRecord
{
    public function __construct(
        public string $affiliateId,
        public ?string $payoutId,
        public int $amountMinor,
        public string $currency,
        public ?int $sequence,
        public bool $fundsReleased,
    ) {}
}
