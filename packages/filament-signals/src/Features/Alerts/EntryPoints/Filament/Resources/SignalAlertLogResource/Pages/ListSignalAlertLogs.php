<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertLogResource\Pages;

use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertLogResource;
use Filament\Resources\Pages\ListRecords;

final class ListSignalAlertLogs extends ListRecords
{
    protected static string $resource = SignalAlertLogResource::class;
}
