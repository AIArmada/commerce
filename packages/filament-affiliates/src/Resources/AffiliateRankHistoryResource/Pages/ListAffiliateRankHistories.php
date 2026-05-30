<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateRankHistoryResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateRankHistoryResource;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateRankHistories extends ListRecords
{
    protected static string $resource = AffiliateRankHistoryResource::class;
}
