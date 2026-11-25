<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

interface PayoutMethodReader
{
    public function hasRecords(string $affiliateId): bool;

    public function hasVerified(string $affiliateId): bool;

    public function defaultType(string $affiliateId): ?string;

    public function defaultProfile(string $affiliateId): ?PayoutMethodProfile;
}
