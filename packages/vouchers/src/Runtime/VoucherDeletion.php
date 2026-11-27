<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

use AIArmada\Vouchers\Models\Voucher;

interface VoucherDeletion
{
    public function handle(string | Voucher $voucher): bool;
}
