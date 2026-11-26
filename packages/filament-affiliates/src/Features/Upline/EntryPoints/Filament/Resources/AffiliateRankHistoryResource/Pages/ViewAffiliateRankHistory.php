<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankHistoryResource\Pages;

use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankHistoryResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewAffiliateRankHistory extends ViewRecord
{
    protected static string $resource = AffiliateRankHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
