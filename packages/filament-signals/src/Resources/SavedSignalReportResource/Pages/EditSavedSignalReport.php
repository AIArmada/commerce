<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SavedSignalReportResource\Pages;

use AIArmada\FilamentSignals\Resources\SavedSignalReportResource;
use AIArmada\FilamentSignals\Support\SavedSignalReportMutationGuard;
use Filament\Resources\Pages\EditRecord;

final class EditSavedSignalReport extends EditRecord
{
    protected static string $resource = SavedSignalReportResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(SavedSignalReportMutationGuard::class)->sanitize($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
