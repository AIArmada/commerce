<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateCreativeResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateCreativeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateCreatives extends ListRecords
{
    protected static string $resource = AffiliateCreativeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
