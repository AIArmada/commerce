<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateNetworkResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateNetworkResource;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateNetworks extends ListRecords
{
    protected static string $resource = AffiliateNetworkResource::class;
}
