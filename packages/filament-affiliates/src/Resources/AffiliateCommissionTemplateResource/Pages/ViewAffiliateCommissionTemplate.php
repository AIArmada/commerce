<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewAffiliateCommissionTemplate extends ViewRecord
{
    protected static string $resource = AffiliateCommissionTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
