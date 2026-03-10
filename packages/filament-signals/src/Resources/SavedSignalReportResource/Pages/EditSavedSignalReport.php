<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SavedSignalReportResource\Pages;

use AIArmada\FilamentSignals\Resources\SavedSignalReportResource;
use Filament\Resources\Pages\EditRecord;

final class EditSavedSignalReport extends EditRecord
{
    protected static string $resource = SavedSignalReportResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
