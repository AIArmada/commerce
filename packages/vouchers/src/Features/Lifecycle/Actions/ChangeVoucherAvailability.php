<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherAvailability;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Paused;
use Illuminate\Validation\ValidationException;

final readonly class ChangeVoucherAvailability implements VoucherAvailability
{
    public function handle(string $voucherId, bool $active): void
    {
        (new Voucher)->getConnection()->transaction(function () use ($voucherId, $active): void {
            $locked = config('vouchers.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(Voucher::class, $voucherId, lockForUpdate: true)
                : Voucher::query()->whereKey($voucherId)->lockForUpdate()->firstOrFail();
            $expected = $active ? Paused::class : Active::class;
            $target = $active ? Active::class : Paused::class;

            if (! ($locked->status instanceof $expected) || $locked->isExpired()) {
                throw ValidationException::withMessages(['status' => 'The voucher is no longer eligible for this transition.']);
            }

            $locked->status->transitionTo($target);
            $locked->save();
        });
    }
}
