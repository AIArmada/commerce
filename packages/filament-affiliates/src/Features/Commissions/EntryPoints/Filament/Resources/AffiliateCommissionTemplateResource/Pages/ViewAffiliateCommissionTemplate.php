<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateCommissionTemplateResource\Pages;

use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateCommissionTemplateResource;
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
