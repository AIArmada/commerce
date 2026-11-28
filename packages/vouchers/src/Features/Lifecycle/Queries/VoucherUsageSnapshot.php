<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Queries;

use AIArmada\Vouchers\Data\VoucherUsageData;
use AIArmada\Vouchers\Features\Lifecycle\Models\VoucherUsage;

final class VoucherUsageSnapshot
{
    public static function fromModel(VoucherUsage $usage): VoucherUsageData
    {
        return new VoucherUsageData(
            id: $usage->id,
            voucherId: $usage->voucher_id,
            discountAmount: $usage->discount_amount,
            currency: $usage->currency,
            channel: $usage->channel,
            redeemedByType: $usage->redeemed_by_type,
            redeemedById: $usage->redeemed_by_id === null ? null : (string) $usage->redeemed_by_id,
            usedAt: $usage->used_at,
            metadata: $usage->metadata,
            notes: $usage->notes,
        );
    }
}
