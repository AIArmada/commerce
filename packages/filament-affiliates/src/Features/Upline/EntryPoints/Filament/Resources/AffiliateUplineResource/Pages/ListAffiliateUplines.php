<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateUplineResource\Pages;

use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateUplineResource;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateUplines extends ListRecords
{
    protected static string $resource = AffiliateUplineResource::class;
}
