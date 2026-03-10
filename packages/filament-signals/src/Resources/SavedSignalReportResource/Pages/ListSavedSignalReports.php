<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SavedSignalReportResource\Pages;

use AIArmada\FilamentSignals\Resources\SavedSignalReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListSavedSignalReports extends ListRecords
{
    protected static string $resource = SavedSignalReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
