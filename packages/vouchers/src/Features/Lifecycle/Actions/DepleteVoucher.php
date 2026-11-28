<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherDepletion;
use AIArmada\Vouchers\Features\Lifecycle\Models\Voucher;
use AIArmada\Vouchers\Features\Lifecycle\States\Active;
use AIArmada\Vouchers\Features\Lifecycle\States\Depleted;
use AIArmada\Vouchers\Features\Lifecycle\States\Paused;

final readonly class DepleteVoucher implements VoucherDepletion
{
    public function handle(string $voucherId): void
    {
        (new Voucher)->getConnection()->transaction(function () use ($voucherId): void {
            $locked = config('vouchers.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(Voucher::class, $voucherId, lockForUpdate: true)
                : Voucher::query()->whereKey($voucherId)->lockForUpdate()->firstOrFail();

            if ($locked->usage_limit === null || $locked->usages()->count() < $locked->usage_limit
                || (! $locked->status instanceof Active && ! $locked->status instanceof Paused)) {
                return;
            }

            $locked->status->transitionTo(Depleted::class);
            $locked->saveOrFail();
        });
    }
}
