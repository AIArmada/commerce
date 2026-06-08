<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSubLocationResource\Pages;

use AIArmada\FilamentEvents\Resources\EventSubLocationResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateEventSubLocation extends CreateRecord
{
    protected static string $resource = EventSubLocationResource::class;
}
