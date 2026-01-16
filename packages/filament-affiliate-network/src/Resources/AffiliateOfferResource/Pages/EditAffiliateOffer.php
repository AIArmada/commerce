<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages;

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditAffiliateOffer extends EditRecord
{
    protected static string $resource = AffiliateOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
