<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

use AIArmada\Vouchers\Models\VoucherWallet;

interface WalletEntryDeletion
{
    public function handle(VoucherWallet $entry): bool;
}
