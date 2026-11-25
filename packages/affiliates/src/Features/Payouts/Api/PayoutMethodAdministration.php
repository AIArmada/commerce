<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

interface PayoutMethodAdministration
{
    public function verify(string $affiliateId, string $methodId): void;

    public function selectDefault(string $affiliateId, string $methodId): void;

    public function setDefault(string $affiliateId, string $type, string $label, string $accountRef): void;
}
