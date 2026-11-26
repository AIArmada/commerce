<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Api;

interface FraudSignalMaintenance
{
    public function assertConnection(?string $name, int $instanceId): void;

    public function deleteForAffiliate(string $affiliateId): void;
}
