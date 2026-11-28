<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Queries;

use AIArmada\Vouchers\Data\VoucherUsageData;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherUsageHistory;
use AIArmada\Vouchers\Features\Lifecycle\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Models\VoucherUsage;

final class ReadVoucherUsageHistory implements VoucherUsageHistory
{
    use QueriesVouchers;

    /** @return list<VoucherUsageData> */
    public function handle(string $code, int $limit = 100): array
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->first();

        if ($voucher === null) {
            return [];
        }

        return $voucher->usages()
            ->latest('used_at')
            ->orderByDesc('id')
            ->limit(max(1, min(1000, $limit)))
            ->get()
            ->map(static fn (VoucherUsage $usage): VoucherUsageData => VoucherUsageSnapshot::fromModel($usage))
            ->values()
            ->all();
    }
}
