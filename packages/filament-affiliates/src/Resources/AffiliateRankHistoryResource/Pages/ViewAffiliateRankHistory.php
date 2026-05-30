<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateRankHistoryResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateRankHistoryResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewAffiliateRankHistory extends ViewRecord
{
    protected static string $resource = AffiliateRankHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
