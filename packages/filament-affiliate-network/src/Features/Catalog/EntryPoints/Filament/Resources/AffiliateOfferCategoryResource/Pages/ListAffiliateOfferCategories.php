<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferCategoryResource\Pages;

use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateOfferCategories extends ListRecords
{
    protected static string $resource = AffiliateOfferCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
