<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Actions;

use AIArmada\Vouchers\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Models\VoucherWallet;
use AIArmada\Vouchers\Runtime\WalletRemoval;
use Illuminate\Database\Eloquent\Model;

final readonly class RemoveVoucherFromWallet implements WalletRemoval
{
    use QueriesVouchers;

    public function handle(string $code, Model $holder): bool
    {
        $voucher = $this->voucherQuery()->where('code', $this->normalizeCode($code))->firstOrFail();

        return VoucherWallet::query()
            ->where('voucher_id', $voucher->getKey())
            ->where('holder_type', $holder->getMorphClass())
            ->where('holder_id', $holder->getKey())
            ->whereNull('redeemed_at')
            ->delete() > 0;
    }
}
