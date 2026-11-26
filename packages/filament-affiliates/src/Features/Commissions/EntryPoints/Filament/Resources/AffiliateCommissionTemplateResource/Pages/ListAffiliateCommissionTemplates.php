<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateCommissionTemplateResource\Pages;

use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateCommissionTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateCommissionTemplates extends ListRecords
{
    protected static string $resource = AffiliateCommissionTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
