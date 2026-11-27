<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Events\VoucherExpired;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\States\Expired;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class ExpireVoucher
{
    use AsAction;
    use QueriesVouchers;

    public function handle(string $code): void
    {
        DB::transaction(function () use ($code): void {
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

            if ($voucher->status instanceof Expired) {
                return;
            }

            if ($voucher->expires_at === null || $voucher->expires_at->isFuture()) {
                $voucher->expires_at = CarbonImmutable::now();
            }

            $voucher->status->transitionTo(Expired::class);
            $voucher->save();

            event(new VoucherExpired(VoucherData::fromModel($voucher)));
        });
    }
}
