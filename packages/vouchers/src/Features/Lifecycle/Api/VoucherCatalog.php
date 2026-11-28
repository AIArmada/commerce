<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Api;

use AIArmada\Vouchers\Data\VoucherData;

interface VoucherCatalog
{
    public function find(string $code, ?string $voucherId = null): ?VoucherData;

    public function invalidate(string $code): void;

    public function getRemainingUses(string $code): int;
}
