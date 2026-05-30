<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateRankResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateRankResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateRanks extends ListRecords
{
    protected static string $resource = AffiliateRankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
