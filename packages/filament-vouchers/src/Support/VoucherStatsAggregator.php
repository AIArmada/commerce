<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Expired;
use AIArmada\Vouchers\States\VoucherStatus;
use Illuminate\Database\Eloquent\Builder;

final class VoucherStatsAggregator
{
    /**
     * @return array{
     *     total: int,
     *     active: int,
     *     upcoming: int,
     *     expired: int,
     *     manual_redemptions: int,
     *     total_discount_minor: int,
     * }
     */
    public function overview(): array
    {
        return [
            'total' => $this->vouchers()->count(),
            'active' => $this->vouchers()->where('status', VoucherStatus::normalize(Active::class))->count(),
            'upcoming' => $this->vouchers()
                ->where('starts_at', '>', now())
                ->count(),
            'expired' => $this->vouchers()->where('status', VoucherStatus::normalize(Expired::class))->count(),
            'manual_redemptions' => $this->usages()->where('channel', VoucherUsage::CHANNEL_MANUAL)->count(),
            'total_discount_minor' => $this->sumDiscountMinor(),
        ];
    }

    /**
     * @return Builder<Voucher>
     */
    private function vouchers(): Builder
    {
        /** @var Builder<Voucher> $query */
        $query = Voucher::query();

        /** @var Builder<Voucher> $scoped */
        $scoped = OwnerQuery::applyToEloquentBuilder(
            $query,
            OwnerContext::resolve(),
            (bool) config('vouchers.owner.include_global', false),
        );

        return $scoped;
    }

    /**
     * @return Builder<VoucherUsage>
     */
    private function usages(): Builder
    {
        /** @var Builder<VoucherUsage> $query */
        $query = VoucherUsage::query();

        if (! config('vouchers.owner.enabled', false)) {
            return $query;
        }

        $voucherQuery = Voucher::query()->select('id');

        $voucherQuery = OwnerQuery::applyToEloquentBuilder(
            $voucherQuery,
            OwnerContext::resolve(),
            (bool) config('vouchers.owner.include_global', false),
        );

        return $query->whereIn('voucher_id', $voucherQuery);
    }

    private function sumDiscountMinor(): int
    {
        // discount_amount is already stored as integer cents
        $sum = $this->usages()->sum('discount_amount');

        return (int) $sum;
    }
}
