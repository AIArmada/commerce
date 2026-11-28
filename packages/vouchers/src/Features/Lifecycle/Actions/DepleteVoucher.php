<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Runtime\VoucherDepletion;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Depleted;
use AIArmada\Vouchers\States\Paused;

final readonly class DepleteVoucher implements VoucherDepletion
{
    public function handle(Voucher $voucher): void
    {
        $voucher->getConnection()->transaction(function () use ($voucher): void {
            $locked = config('vouchers.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(Voucher::class, $voucher->getKey(), lockForUpdate: true)
                : Voucher::query()->whereKey($voucher->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->usage_limit === null || $locked->usages()->count() < $locked->usage_limit
                || (! $locked->status instanceof Active && ! $locked->status instanceof Paused)) {
                return;
            }

            $locked->status->transitionTo(Depleted::class);
            $locked->saveOrFail();
            $voucher->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
