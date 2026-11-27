<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

interface VoucherExpiry
{
    public function handle(string $code): void;
}
