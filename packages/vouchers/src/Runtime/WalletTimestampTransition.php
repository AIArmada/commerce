<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

use AIArmada\Vouchers\Models\VoucherWallet;

interface WalletTimestampTransition
{
    public function handle(VoucherWallet $wallet, bool $redeemed): void;
}
