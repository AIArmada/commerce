<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

use AIArmada\Vouchers\Models\Voucher;

interface VoucherUpdater
{
    /** @param array<string, mixed> $data */
    public function handle(string | Voucher $voucher, array $data): Voucher;
}
