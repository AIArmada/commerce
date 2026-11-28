<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

use AIArmada\Vouchers\Models\VoucherUsage;
use Illuminate\Database\Eloquent\Collection;

interface VoucherUsageHistory
{
    /** @return Collection<int, VoucherUsage> */
    public function handle(string $code, int $limit = 100): Collection;
}
