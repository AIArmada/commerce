<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferCategoryResource\Pages;

use AIArmada\AffiliateNetwork\Features\Catalog\Actions\CreateCategory;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferCategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateAffiliateOfferCategory extends CreateRecord
{
    protected static string $resource = AffiliateOfferCategoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $parentId = $data['parent_id'] ?? null;

        if (is_scalar($parentId) && (string) $parentId !== '') {
            /** @var AffiliateOfferCategory $parent */
            $parent = OwnerContext::withOwner(null, fn (): AffiliateOfferCategory => AffiliateOfferCategory::query()
                ->withoutOwnerScope()
                ->whereKey((string) $parentId)
                ->firstOrFail());

            $data['parent_id'] = (string) $parent->getKey();
        }

        return app(CreateCategory::class)->execute($data);
    }
}
