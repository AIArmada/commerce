<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Models\VoucherWallet;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;
use AIArmada\Vouchers\Runtime\WalletEntryDeletion;

final readonly class DeleteWalletEntry implements WalletEntryDeletion
{
    public function handle(VoucherWallet $entry): bool
    {
        return $entry->getConnection()->transaction(function () use ($entry): bool {
            $locked = config('vouchers.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(VoucherWallet::class, $entry->getKey(), lockForUpdate: true)
                : VoucherWallet::query()->whereKey($entry->getKey())->lockForUpdate()->first();

            return $locked instanceof VoucherWallet && $locked->delete() === true;
        });
    }

    /** @param Collection<int, VoucherWallet> $records */
    public function handleMany(Collection $records): void
    {
        if ($records->isEmpty()) {
            return;
        }

        (new VoucherWallet)->getConnection()->transaction(function () use ($records): void {
            foreach ($records->sortBy(static fn (VoucherWallet $record): string => (string) $record->getKey()) as $record) {
                if (! $this->handle($record)) {
                    throw new RuntimeException('Unable to delete entry in the selected batch.');
                }
            }
        });
    }
}
