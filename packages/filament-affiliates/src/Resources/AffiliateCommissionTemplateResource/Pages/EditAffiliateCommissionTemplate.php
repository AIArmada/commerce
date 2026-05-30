<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditAffiliateCommissionTemplate extends EditRecord
{
    protected static string $resource = AffiliateCommissionTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
