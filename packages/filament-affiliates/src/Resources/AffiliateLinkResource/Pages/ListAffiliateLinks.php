<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateLinks extends ListRecords
{
    protected static string $resource = AffiliateLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
