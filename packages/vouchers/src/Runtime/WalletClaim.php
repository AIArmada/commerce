<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherWallet;
use Illuminate\Database\Eloquent\Model;

interface WalletClaim
{
    /** @param array<string, mixed>|null $metadata */
    public function handle(string | Voucher $code, Model $holder, ?array $metadata = null): VoucherWallet;
}
