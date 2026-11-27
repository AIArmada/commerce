<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

use AIArmada\Vouchers\Models\Voucher;

interface VoucherAvailability
{
    public function handle(Voucher $voucher, bool $active): void;
}
