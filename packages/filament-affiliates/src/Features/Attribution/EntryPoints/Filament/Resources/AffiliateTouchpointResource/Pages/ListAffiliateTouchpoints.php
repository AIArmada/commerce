<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Resources\AffiliateTouchpointResource\Pages;

use AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Resources\AffiliateTouchpointResource;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateTouchpoints extends ListRecords
{
    protected static string $resource = AffiliateTouchpointResource::class;
}
