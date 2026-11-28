<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Queries;

use AIArmada\Vouchers\Features\Lifecycle\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Runtime\VoucherUsageHistory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class ReadVoucherUsageHistory implements VoucherUsageHistory
{
    use QueriesVouchers;

    /**
     * @return EloquentCollection<int, VoucherUsage>
     */
    public function handle(string $code, int $limit = 100): EloquentCollection
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->first();

        if (! $voucher) {
            return new EloquentCollection;
        }

        /** @var EloquentCollection<int, VoucherUsage> $result */
        $result = $voucher->usages()
            ->latest('used_at')
            ->limit(max(1, $limit))
            ->get();

        return $result;
    }
}
