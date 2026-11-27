<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

use AIArmada\Vouchers\Models\Voucher;

interface VoucherDepletion
{
    public function handle(Voucher $voucher): void;
}
