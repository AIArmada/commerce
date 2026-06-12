<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSessionResource\Pages;

use AIArmada\FilamentEvents\Resources\EventSessionResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewEventSession extends ViewRecord
{
    protected static string $resource = EventSessionResource::class;
}
