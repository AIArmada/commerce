<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliatePayouts extends ListRecords
{
    protected static string $resource = AffiliatePayoutResource::class;
}
