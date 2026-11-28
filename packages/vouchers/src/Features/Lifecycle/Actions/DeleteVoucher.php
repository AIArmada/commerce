<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Features\Lifecycle\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Runtime\VoucherDeletion;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

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
            $locked = config('vouchers.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(Voucher::class, $record->getKey(), lockForUpdate: true)
                : Voucher::query()->whereKey($record->getKey())->lockForUpdate()->first();

            return $locked instanceof Voucher && $locked->delete() === true;
        });
    }

    /** @param Collection<int, Voucher> $records */
    public function handleMany(Collection $records): void
    {
        if ($records->isEmpty()) {
            return;
        }

        (new Voucher)->getConnection()->transaction(function () use ($records): void {
            foreach ($records->sortBy(static fn (Voucher $record): string => (string) $record->getKey()) as $record) {
                if (! $this->handle($record)) {
                    throw new RuntimeException('Unable to delete voucher in the selected batch.');
                }
            }
        });
    }
}
