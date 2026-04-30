<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource\Pages;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliateOfferCategory extends CreateRecord
{
    protected static string $resource = AffiliateOfferCategoryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['parent_id']) && $data['parent_id'] !== null && $data['parent_id'] !== '') {
            if (config('affiliate-network.owner.enabled', false)) {
                /** @var AffiliateOfferCategory $parent */
                $parent = OwnerWriteGuard::findOrFailForOwner(
                    AffiliateOfferCategory::class,
                    (string) $data['parent_id'],
                    includeGlobal: (bool) config('affiliate-network.owner.include_global', false),
                    message: 'Parent category is not accessible in the current owner scope.',
                );
            } else {
                $parent = AffiliateOfferCategory::query()->whereKey((string) $data['parent_id'])->firstOrFail();
            }

            $data['parent_id'] = (string) $parent->getKey();
        }

        return $data;
    }
}
