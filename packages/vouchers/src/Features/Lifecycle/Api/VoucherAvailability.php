<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Api;

interface VoucherAvailability
{
    public function handle(string $voucherId, bool $active): void;
}
