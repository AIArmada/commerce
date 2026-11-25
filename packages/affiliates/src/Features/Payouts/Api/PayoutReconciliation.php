<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

interface PayoutReconciliation
{
    public const COMPLETION_BLOCKED_CODE = 'PAYOUT_COMPLETION_BLOCKED';

    /** @param array<string, mixed> $externalData */
    public function reconcile(string $payoutId, string $externalStatus, array $externalData = []): bool;
}
