<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Events\VoucherExpired;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Features\Lifecycle\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Runtime\VoucherExpiry;
use AIArmada\Vouchers\Runtime\VoucherSnapshot;
use AIArmada\Vouchers\States\Depleted;
use AIArmada\Vouchers\States\Expired;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

final class ExpireVoucher implements VoucherExpiry
{
    use AsAction;
    use QueriesVouchers;

    public function handle(string $code): void
    {
        (new Voucher)->getConnection()->transaction(function () use ($code): void {
            $voucher = $this->voucherQuery()
                ->where('code', $this->normalizeCode($code))
                ->lockForUpdate()
                ->first();

            if (! $voucher) {
                throw VoucherNotFoundException::withCode($code);
            }

            if (config('vouchers.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(Voucher::class, $voucher->getKey());
            }

            if ($voucher->status instanceof Expired || $voucher->status instanceof Depleted) {
                return;
            }

            if ($voucher->expires_at === null || $voucher->expires_at->isFuture()) {
                $voucher->expires_at = CarbonImmutable::now();
            }

            $voucher->status->transitionTo(Expired::class);
            $voucher->save();

            event(new VoucherExpired(VoucherSnapshot::fromModel($voucher)));
        });
    }
}
