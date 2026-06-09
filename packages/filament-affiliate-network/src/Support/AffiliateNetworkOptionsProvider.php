<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Support;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerContext;

final class AffiliateNetworkOptionsProvider
{
    /**
     * @return array<string, string>
     */
    public static function verifiedSiteOptions(): array
    {
        return OwnerContext::withOwner(null, fn () => AffiliateSite::query()
            ->withoutOwnerScope()
            ->where('status', AffiliateSite::STATUS_VERIFIED)
            ->pluck('name', 'id')
            ->all());
    }

    /**
     * @return array<string, string>
     */
    public static function activeCategoryOptions(): array
    {
        return OwnerContext::withOwner(null, fn () => AffiliateOfferCategory::query()
            ->withoutOwnerScope()
            ->where('is_active', true)
            ->pluck('name', 'id')
            ->all());
    }

    /**
     * @return array<string, string>
     */
    public static function parentCategoryOptions(?string $excludeId = null): array
    {
        return OwnerContext::withOwner(null, function () use ($excludeId): array {
            $query = AffiliateOfferCategory::query()
                ->withoutOwnerScope();

            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }

            return $query->pluck('name', 'id')->all();
        });
    }
}
