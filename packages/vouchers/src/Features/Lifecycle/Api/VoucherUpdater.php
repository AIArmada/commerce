<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Api;

use AIArmada\Vouchers\Data\VoucherData;

interface VoucherUpdater
{
    /** @param array<string, mixed> $data */
    public function handle(string $voucherId, array $data): VoucherData;
}
