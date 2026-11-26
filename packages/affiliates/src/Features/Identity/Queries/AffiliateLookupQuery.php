<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Features\Attribution\Api\AttributionLookup;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\CommerceSupport\Support\ConnectionDriver;
use AIArmada\CommerceSupport\Support\LikeSearch;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final readonly class AffiliateLookupQuery
{
    public function __construct(private AttributionLookup $attributions) {}

    /** @return Builder<Affiliate> */
    private function query(): Builder
    {
        $query = Affiliate::query();

        if (! config('affiliates.owner.enabled', false)) {
            return $query;
        }

        return $query->forOwner();
    }

    public function codeAvailable(string $code, ?string $ignoreAffiliateId = null): bool
    {
        if (! preg_match('/\\A[A-Za-z0-9_-]{1,64}\\z/D', $code)
            || ($ignoreAffiliateId !== null && ! Str::isUuid($ignoreAffiliateId))) {
            return false;
        }

        $query = Affiliate::query()->withoutOwnerScope()->whereRaw('LOWER(code) = ?', [mb_strtolower($code)]);

        if ($ignoreAffiliateId !== null) {
            $query->whereKeyNot($ignoreAffiliateId);
        }

        return ! $query->exists();
    }

    public function findByCode(string $code): ?Affiliate
    {
        $normalized = mb_trim($code);

        if ($normalized === '') {
            return null;
        }

        $matches = $this->query()->whereRaw('LOWER(code) = ?', [mb_strtolower($normalized)])->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    public function findByDefaultVoucherCode(string $voucherCode): ?Affiliate
    {
        return $this->findByColumn($this->query(), 'default_voucher_code', $voucherCode);
    }

    public function findById(string $id): ?Affiliate
    {
        return Str::isUuid($id) ? $this->query()->whereKey($id)->first() : null;
    }

    public function findActiveAffiliateByCookie(string $cookieValue): ?Affiliate
    {
        $id = $this->attributions->affiliateIdForCookie($cookieValue);
        $affiliate = $id === null ? null : $this->findById($id);

        return $affiliate?->canBeAttributed() ? $affiliate : null;
    }

    /** @param Builder<Affiliate> $query */
    private function findByColumn(Builder $query, string $column, string $value): ?Affiliate
    {
        $normalized = mb_trim($value);
        if ($normalized === '') {
            return null;
        }

        /** @var Connection $connection */
        $connection = $query->getConnection();
        $driver = ConnectionDriver::name($connection);

        $matches = $query
            ->when(
                $driver === 'pgsql',
                fn (Builder $builder) => $builder->whereRaw(
                    $column . ' ILIKE ? ' . LikeSearch::escapeClause($builder),
                    [LikeSearch::escape($normalized)]
                ),
                fn (Builder $builder) => $builder->whereRaw('LOWER(' . $column . ') = ?', [mb_strtolower($normalized)]),
            )
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
