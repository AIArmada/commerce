<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSeriesResource\Pages;

use AIArmada\FilamentEvents\Resources\EventSeriesResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateEventSeries extends CreateRecord
{
    protected static string $resource = EventSeriesResource::class;
}
