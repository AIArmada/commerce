<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalAlertLogResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalAlertLogResource;
use Filament\Resources\Pages\ListRecords;

final class ListSignalAlertLogs extends ListRecords
{
    protected static string $resource = SignalAlertLogResource::class;
}
