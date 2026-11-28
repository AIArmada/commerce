<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Api;

interface VoucherExpiry
{
    public function handle(string $code): void;
}
