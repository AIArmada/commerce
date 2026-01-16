<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Pages;

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditAffiliateSite extends EditRecord
{
    protected static string $resource = AffiliateSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
