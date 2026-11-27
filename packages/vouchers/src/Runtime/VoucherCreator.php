<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

use AIArmada\Vouchers\Models\Voucher;

interface VoucherCreator
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): Voucher;
}
