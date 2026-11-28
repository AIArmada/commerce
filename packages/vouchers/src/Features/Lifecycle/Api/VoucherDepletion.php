<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Api;

interface VoucherDepletion
{
    public function handle(string $voucherId): void;
}
