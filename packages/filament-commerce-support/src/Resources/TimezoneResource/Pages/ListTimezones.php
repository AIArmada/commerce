<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Resources\TimezoneResource\Pages;

use AIArmada\FilamentCommerceSupport\Resources\TimezoneResource;
use Filament\Resources\Pages\ListRecords;

final class ListTimezones extends ListRecords
{
    protected static string $resource = TimezoneResource::class;
}
