<?php
declare(strict_types=1);
namespace AIArmada\FilamentEvents\Resources\EventChangeLogResource\Pages;

use AIArmada\FilamentEvents\Resources\EventChangeLogResource;
use Filament\Resources\Pages\ListRecords;

final class ListEventChangeLogs extends ListRecords
{
    protected static string $resource = EventChangeLogResource::class;
}
