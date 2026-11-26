<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

interface PayoutConversionSelection
{
    /** @param list<string> $conversionIds */
    public function manual(array $conversionIds, int $connectionIdentity): PayoutSelectionRecord;

    public function scheduled(string $affiliateId, int $availableMinor, string $currency, bool $lockForUpdate, int $connectionIdentity): PayoutAllocation;
}
