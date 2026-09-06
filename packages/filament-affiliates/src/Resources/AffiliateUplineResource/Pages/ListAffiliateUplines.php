<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateUplineResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateUplineResource;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateUplines extends ListRecords
{
    protected static string $resource = AffiliateUplineResource::class;
}
