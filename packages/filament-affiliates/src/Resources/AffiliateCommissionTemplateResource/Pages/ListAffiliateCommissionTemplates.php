<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource;
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
