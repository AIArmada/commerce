<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Resources\ContactMethodResource\Pages;

use AIArmada\FilamentContacting\Resources\ContactMethodResource;
use Filament\Resources\Pages\ListRecords;

final class ListContactMethods extends ListRecords
{
    protected static string $resource = ContactMethodResource::class;
}
