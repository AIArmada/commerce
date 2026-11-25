<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

interface PayoutConversionIntegrity
{
    public function hasConversions(string $payoutId, int $connectionIdentity): bool;

    /** @return list<string> */
    public function currencies(string $payoutId, int $connectionIdentity): array;
}
