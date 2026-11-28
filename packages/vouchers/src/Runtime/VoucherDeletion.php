<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Database\Eloquent\Collection;

interface VoucherDeletion
{
    public function handle(string | Voucher $voucher): bool;

    /** @param Collection<int, Voucher> $records */
    public function handleMany(Collection $records): void;
}
