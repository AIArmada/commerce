<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages;

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

        if (isset($data['category_id']) && $data['category_id'] !== null && $data['category_id'] !== '') {
            /** @var AffiliateOfferCategory $category */
            $category = OwnerContext::withOwner(null, fn (): AffiliateOfferCategory => AffiliateOfferCategory::query()
                ->withoutOwnerScope()
                ->whereKey((string) $data['category_id'])
                ->firstOrFail());

            $data['category_id'] = (string) $category->getKey();
        }

        return $data;
    }

    /**
     * Wrap model creation in explicit global owner context so the ScopesBySiteOwner
     * creating-hook does not throw when affiliate-network.owner.enabled=true.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return OwnerContext::withOwner(null, fn (): Model => parent::handleRecordCreation($data));
    }
}
