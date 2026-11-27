<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Runtime\VoucherDeletion;

final class DeleteVoucher implements VoucherDeletion
{
    use QueriesVouchers;

    public function handle(string | Voucher $voucher): bool
    {
        $record = $voucher instanceof Voucher
            ? $voucher
            : $this->voucherQuery()->where('code', $this->normalizeCode($voucher))->first();

        if (! $record instanceof Voucher) {
            return false;
        }

        return $record->getConnection()->transaction(function () use ($record): bool {
            $this->authorize($record);

            $locked = Voucher::query()->whereKey($record->getKey())->lockForUpdate()->first();

            return $locked instanceof Voucher && $locked->delete() === true;
        });
    }

    private function authorize(Voucher $voucher): void
    {
        if (config('vouchers.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(Voucher::class, $voucher->getKey());
        }
    }
}
