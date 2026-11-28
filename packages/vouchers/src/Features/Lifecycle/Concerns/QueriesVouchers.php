<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Concerns;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Vouchers\Concerns\NormalizesVoucherCodes;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Provides owner-scoped voucher query helpers.
 */
trait QueriesVouchers
{
    use NormalizesVoucherCodes;

    /**
     * Build an owner-scoped voucher query. Only eligibility and remaining-use
     * reads need the aggregate redemption count.
     *
     * @return Builder<Voucher>
     */
    protected function voucherQuery(bool $withUsageCount = false): Builder
    {
        $owner = $this->resolveOwner();
        $includeGlobal = $this->shouldIncludeGlobal();

        $query = Voucher::query()->forOwner($owner, $includeGlobal);

        if ($withUsageCount) {
            $query->withCount('usages');
        }

        if ($owner !== null && $includeGlobal) {
            $query->orderByRaw('CASE WHEN owner_id IS NULL THEN 1 ELSE 0 END');
        }

        return $query;
    }

    /** @return Builder<Voucher> */
    protected function voucherIdentityQuery(string $code, ?string $voucherId): Builder
    {
        $query = $this->voucherQuery();

        return $voucherId === null
            ? $query->where('code', $this->normalizeCode($code))
            : $query->whereKey($voucherId);
    }

    protected function resolveOwner(): ?Model
    {
        if (! config('vouchers.owner.enabled', false)) {
            return null;
        }

        $owner = OwnerContext::resolveConfigured(Voucher::ownerScopeConfig());
        OwnerContext::assertResolvedOrExplicitGlobal($owner);

        return $owner;
    }

    protected function shouldIncludeGlobal(): bool
    {
        return ! OwnerScopeOverride::suppressIncludeGlobal()
            && (bool) config('vouchers.owner.include_global', false);
    }
}
