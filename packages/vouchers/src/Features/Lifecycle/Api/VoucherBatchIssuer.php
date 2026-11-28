<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Api;

use AIArmada\Vouchers\Data\VoucherType;

interface VoucherBatchIssuer
{
    public function handle(int $count, string $prefix, string $name, VoucherType $type, int $value, string $currency, ?int $usageLimit): int;
}
