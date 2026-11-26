<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankResource\Pages;

use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankResource;
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
