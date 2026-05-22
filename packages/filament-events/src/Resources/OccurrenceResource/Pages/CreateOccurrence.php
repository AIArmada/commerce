<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\OccurrenceResource\Pages;

use AIArmada\FilamentEvents\Resources\OccurrenceResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateOccurrence extends CreateRecord
{
    protected static string $resource = OccurrenceResource::class;
}
