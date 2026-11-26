<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

interface PayoutReservation
{
    /** @param list<string> $conversionIds */
    public function reserve(string $balanceId, string $affiliateId, string $payoutId, string $currency, int $amountMinor, int $sequence, array $conversionIds): void;
}
