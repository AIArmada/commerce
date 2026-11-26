<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final readonly class CountFraudClick
{
    public function execute(string $affiliateId, ?string $ipAddress): int
    {
        $key = "fraud:clicks:{$affiliateId}:{$ipAddress}";

        Cache::add($key, 0, CarbonImmutable::now()->addHour());

        return max(0, (int) Cache::increment($key) - 1);
    }
}
