<?php
declare(strict_types=1);
namespace AIArmada\FilamentEvents\Resources\EventChangeLogResource\Pages;

use AIArmada\FilamentEvents\Resources\EventChangeLogResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewEventChangeLog extends ViewRecord
{
    protected static string $resource = EventChangeLogResource::class;
}
