<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Api;

final readonly class DailyStatRecord
{
    public function __construct(public string $id, public string $currency, public int $clicks, public int $uniqueClicks, public int $attributions, public int $conversions, public int $revenueMinor, public int $commissionMinor) {}
}
