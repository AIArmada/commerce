<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource\Pages;

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditAffiliateOfferCategory extends EditRecord
{
    protected static string $resource = AffiliateOfferCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
