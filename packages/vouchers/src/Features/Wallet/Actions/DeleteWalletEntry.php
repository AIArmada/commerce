<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Features\Wallet\Api\WalletEntryDeletion;
use AIArmada\Vouchers\Models\VoucherWallet;
use RuntimeException;

final readonly class DeleteWalletEntry implements WalletEntryDeletion
{
    public function handle(string $walletId): bool
    {
        return (new VoucherWallet)->getConnection()->transaction(function () use ($walletId): bool {
            $locked = config('vouchers.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(VoucherWallet::class, $walletId, lockForUpdate: true)
                : VoucherWallet::query()->whereKey($walletId)->lockForUpdate()->first();

            return $locked instanceof VoucherWallet && $locked->delete() === true;
        });
    }

    /** @param list<string> $walletIds */
    public function handleMany(array $walletIds): void
    {
        if ($walletIds === []) {
            return;
        }

        $walletIds = array_values(array_unique($walletIds));
        sort($walletIds, SORT_STRING);

        (new VoucherWallet)->getConnection()->transaction(function () use ($walletIds): void {
            foreach ($walletIds as $walletId) {
                if (! $this->handle($walletId)) {
                    throw new RuntimeException('Unable to delete entry in the selected batch.');
                }
            }
        });
    }
}
