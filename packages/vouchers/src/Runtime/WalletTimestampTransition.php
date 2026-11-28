<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

use AIArmada\Vouchers\Models\VoucherWallet;
use Illuminate\Database\Eloquent\Collection;

interface WalletTimestampTransition
{
    public function handle(VoucherWallet $wallet, bool $redeemed): void;

    /** @param Collection<int, VoucherWallet> $records */
    public function handleMany(Collection $records, bool $redeemed): void;
}
