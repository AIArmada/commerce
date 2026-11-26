<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Queries;

use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionLookup;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateAttribution;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final readonly class ReadAttributions implements AttributionLookup
{
    public function affiliateIdForCookie(string $cookieValue): ?string
    {
        $affiliate = $this->findActiveAttributionByCookie($cookieValue)?->affiliate;

        return $affiliate?->canBeAttributed() ? (string) $affiliate->getKey() : null;
    }

    public function attached(string $identifier, string $instance): ?AffiliateAttributionData
    {
        $query = AffiliateAttribution::query()
            ->where('cart_identifier', $identifier)
            ->where('cart_instance', $instance)
            ->active()
            ->latest('last_seen_at')
            ->latest('id');

        $this->applyOwnerScope($query);

        return $query->first()?->toData();
    }

    public function findActiveAttributionByCookie(string $cookieValue): ?AffiliateAttribution
    {
        return $this->findAttributionByCookie($cookieValue);
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
            ->latest('last_cookie_seen_at')
            ->latest('id');

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
