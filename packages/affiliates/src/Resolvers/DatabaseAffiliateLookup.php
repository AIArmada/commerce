<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Resolvers;

use AIArmada\Affiliates\Contracts\AffiliateLookup;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Cart\Cart;
use AIArmada\CommerceSupport\Support\ConnectionDriver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class DatabaseAffiliateLookup implements AffiliateLookup
{
    /** @return Builder<Affiliate> */
    private function query(): Builder
    {
        $query = Affiliate::query();

        if (! config('affiliates.owner.enabled', false)) {
            return $query;
        }

        return $query->forOwner();
    }

    public function findByCode(string $code): ?Affiliate
    {
        return $this->findByColumn($this->query(), 'code', $code);
    }

    public function findByDefaultVoucherCode(string $voucherCode): ?Affiliate
    {
        return $this->findByColumn($this->query(), 'default_voucher_code', $voucherCode);
    }

    public function findById(string $id): ?Affiliate
    {
        return $this->query()->whereKey($id)->first();
    }

    public function findActiveAffiliateByCookie(string $cookieValue): ?Affiliate
    {
        $attribution = $this->findActiveAttributionByCookie($cookieValue);
        $affiliate = $attribution?->affiliate;

        return $affiliate instanceof Affiliate && $affiliate->isActive() ? $affiliate : null;
    }

    public function findActiveAttributionByCookie(string $cookieValue): ?AffiliateAttribution
    {
        return $this->findAttributionByCookie($cookieValue);
    }

    public function findAttachedAttribution(Cart $cart): ?AffiliateAttribution
    {
        $query = AffiliateAttribution::query()
            ->with('affiliate')
            ->where('cart_identifier', $cart->getIdentifier())
            ->where('cart_instance', $cart->instance())
            ->active()
            ->latest('last_seen_at')
            ->latest('id');

        $this->applyOwnerScope($query);

        return $query->first();
    }

    /** @param Builder<Affiliate> $query */
    private function findByColumn(Builder $query, string $column, string $value): ?Affiliate
    {
        $normalized = mb_trim($value);
        /** @var Connection $connection */
        $connection = $query->getConnection();
        $driver = ConnectionDriver::name($connection);

        return $query
            ->when(
                $driver === 'pgsql',
                fn (Builder $builder) => $builder->whereRaw($column . ' ILIKE ?', [$normalized]),
                fn (Builder $builder) => $builder->whereRaw('LOWER(' . $column . ') = ?', [mb_strtolower($normalized)]),
            )
            ->first();
    }

    private function findAttributionByCookie(?string $cookieValue): ?AffiliateAttribution
    {
        if (! is_string($cookieValue) || $cookieValue === '') {
            return null;
        }

        $candidates = [$cookieValue];

        try {
            $decrypted = decrypt($cookieValue);

            if (is_string($decrypted) && $decrypted !== '') {
                $candidates[] = $decrypted;
            }
        } catch (Throwable) {
        }

        $query = AffiliateAttribution::query()
            ->with('affiliate')
            ->whereIn('cookie_value', array_values(array_unique($candidates)))
            ->active()
            ->latest('last_cookie_seen_at');

        $this->applyOwnerScope($query);

        return $query->first();
    }

    private function applyOwnerScope(Builder $query): void
    {
        if (! config('affiliates.owner.enabled', false)) {
            return;
        }

        OwnerQuery::applyToEloquentBuilder(
            $query,
            OwnerContext::resolve(),
            (bool) config('affiliates.owner.include_global', false),
        );
    }
}
