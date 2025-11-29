<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditAffiliate extends EditRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
