<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Api;

use AIArmada\Vouchers\Data\VoucherUsageData;

interface VoucherUsageHistory
{
    /** @return list<VoucherUsageData> */
    public function handle(string $code, int $limit = 100): array;
}
