<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages;

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateOffers extends ListRecords
{
    protected static string $resource = AffiliateOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
