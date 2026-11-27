<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Actions;

use AIArmada\Vouchers\Models\VoucherWallet;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Runtime\WalletCleanup;

final readonly class DeleteVoucherWalletEntries implements WalletCleanup
{
    public function handle(string $voucherId): void
    {
        if (config('vouchers.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(Voucher::class, $voucherId);
        }

        VoucherWallet::query()->withoutOwnerScope()->where('voucher_id', $voucherId)->delete();
    }
}
