<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliatePrograms extends ListRecords
{
    protected static string $resource = AffiliateProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
