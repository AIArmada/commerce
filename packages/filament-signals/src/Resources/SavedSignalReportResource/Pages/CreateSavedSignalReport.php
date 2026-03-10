<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SavedSignalReportResource\Pages;

use AIArmada\FilamentSignals\Resources\SavedSignalReportResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateSavedSignalReport extends CreateRecord
{
    protected static string $resource = SavedSignalReportResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
