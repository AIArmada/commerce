<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherDeletion;
use AIArmada\Vouchers\Features\Lifecycle\Models\Voucher;
use RuntimeException;

final class DeleteVoucher implements VoucherDeletion
{
    public function handle(string $voucherId): bool
    {
        return (new Voucher)->getConnection()->transaction(function () use ($voucherId): bool {
            $locked = config('vouchers.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(Voucher::class, $voucherId, lockForUpdate: true)
                : Voucher::query()->whereKey($voucherId)->lockForUpdate()->first();

            return $locked instanceof Voucher && $locked->delete() === true;
        });
    }

    /** @param list<string> $voucherIds */
    public function handleMany(array $voucherIds): void
    {
        if ($voucherIds === []) {
            return;
        }

        $voucherIds = array_values(array_unique($voucherIds));
        sort($voucherIds, SORT_STRING);

        (new Voucher)->getConnection()->transaction(function () use ($voucherIds): void {
            foreach ($voucherIds as $voucherId) {
                if (! $this->handle($voucherId)) {
                    throw new RuntimeException('Unable to delete voucher in the selected batch.');
                }
            }
        });
    }
}
