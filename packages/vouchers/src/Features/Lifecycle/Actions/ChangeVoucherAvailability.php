<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Actions;

use AIArmada\Vouchers\Runtime\VoucherAvailability;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Paused;
use Illuminate\Validation\ValidationException;

final readonly class ChangeVoucherAvailability implements VoucherAvailability
{
    public function handle(Voucher $voucher, bool $active): void
    {
        $voucher->getConnection()->transaction(function () use ($voucher, $active): void {
            if (config('vouchers.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(Voucher::class, $voucher->getKey());
            }

            $locked = Voucher::query()->whereKey($voucher->getKey())->lockForUpdate()->firstOrFail();
            $expected = $active ? Paused::class : Active::class;
            $target = $active ? Active::class : Paused::class;

            if (! ($locked->status instanceof $expected) || $locked->isExpired()) {
                throw ValidationException::withMessages(['status' => 'The voucher is no longer eligible for this transition.']);
            }

            $locked->status->transitionTo($target);
            $locked->save();
            $voucher->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
