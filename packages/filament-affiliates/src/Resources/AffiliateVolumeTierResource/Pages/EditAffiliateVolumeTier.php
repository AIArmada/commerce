<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateVolumeTierResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateVolumeTierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditAffiliateVolumeTier extends EditRecord
{
    protected static string $resource = AffiliateVolumeTierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
