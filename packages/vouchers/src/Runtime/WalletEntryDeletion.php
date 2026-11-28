<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

use AIArmada\Vouchers\Models\VoucherWallet;
use Illuminate\Database\Eloquent\Collection;

interface WalletEntryDeletion
{
    public function handle(VoucherWallet $entry): bool;

    /** @param Collection<int, VoucherWallet> $records */
    public function handleMany(Collection $records): void;
}
