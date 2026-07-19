<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTermResource\Pages;

use AIArmada\FilamentEvents\Resources\EventTermResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateEventTerm extends CreateRecord
{
    protected static string $resource = EventTermResource::class;
}
