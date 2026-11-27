<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\Vouchers\Models\Voucher;

final readonly class RecordVoucherApplication
{
    public function handle(string $voucherId, int|string|null $ownerId, ?string $ownerType): void
    {
        if (! config('vouchers.tracking.track_applications', true) || $voucherId === '') {
            return;
        }

        if (($ownerId === null) !== ($ownerType === null)) {
            return;
        }

        $query = Voucher::withoutGlobalScopes()->whereKey($voucherId);

        if ($ownerId === null) {
            $query->whereNull('owner_id')->whereNull('owner_type');
        } else {
            $query->where('owner_id', $ownerId)->where('owner_type', $ownerType);
        }

        $query->increment('applied_count');
    }
}
