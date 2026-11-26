<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Api;

interface FraudSignalReader
{
    /** Trusted integration lookup by exact ID, independent of ambient owner. */
    public function findUnscoped(string $signalId): ?FraudSignalSnapshot;
}
