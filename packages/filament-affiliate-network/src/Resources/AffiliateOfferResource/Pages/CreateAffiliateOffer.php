<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateAffiliateOffer extends CreateRecord
{
    protected static string $resource = AffiliateOfferResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var AffiliateSite $site */
        $site = OwnerContext::withOwner(null, fn (): AffiliateSite => AffiliateSite::query()
            ->withoutOwnerScope()
            ->whereKey((string) $data['site_id'])
            ->firstOrFail());

        $data['site_id'] = (string) $site->getKey();

        $categoryId = $data['category_id'] ?? null;

        if (is_scalar($categoryId) && (string) $categoryId !== '') {
            /** @var AffiliateOfferCategory $category */
            $category = OwnerContext::withOwner(null, fn (): AffiliateOfferCategory => AffiliateOfferCategory::query()
                ->withoutOwnerScope()
                ->whereKey((string) $categoryId)
                ->firstOrFail());

            $data['category_id'] = (string) $category->getKey();
        }

        if (array_key_exists('volume_tiers', $data)) {
            $data['volume_tiers'] = AffiliateOffer::normalizeVolumeTiers(
                is_array($data['volume_tiers']) ? $data['volume_tiers'] : null,
                is_string($data['currency'] ?? null) ? $data['currency'] : null,
            );
        }

        return $data;
    }

    /**
     * Wrap model creation in explicit global owner context so the belongs-to owner
     * creating-hook does not throw when affiliate-network.owner.enabled=true.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return OwnerContext::withOwner(null, fn (): Model => parent::handleRecordCreation($data));
    }
}
