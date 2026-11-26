<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankHistoryResource\Pages;

use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankHistoryResource;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateRankHistories extends ListRecords
{
    protected static string $resource = AffiliateRankHistoryResource::class;
}
