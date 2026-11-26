<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

interface PayoutConversionSettlement
{
    public function complete(string $payoutId): void;

    /**
     * Called by the owning Payouts release action inside its transaction.
     * Return true only when a reserved balance was credited; the caller must
     * persist the operation release marker before committing.
     */
    public function release(string $payoutId): bool;
}
