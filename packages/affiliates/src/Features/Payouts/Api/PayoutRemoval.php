<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

interface PayoutRemoval
{
    public function deleteUnused(string $payoutId): bool;

    public function removeUnusedConfiguration(string $affiliateId): void;
}
