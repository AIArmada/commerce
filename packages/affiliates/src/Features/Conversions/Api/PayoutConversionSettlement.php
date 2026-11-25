<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

interface PayoutConversionSettlement
{
    public function complete(string $payoutId): void;

    /** Return true only when a reserved balance was credited. */
    public function release(string $payoutId): bool;
}
