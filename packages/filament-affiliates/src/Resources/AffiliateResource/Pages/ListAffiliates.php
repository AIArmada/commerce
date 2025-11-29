<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliates extends ListRecords
{
    protected static string $resource = AffiliateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
