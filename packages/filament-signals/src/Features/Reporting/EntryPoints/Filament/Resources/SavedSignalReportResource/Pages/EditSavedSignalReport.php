<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Resources\SavedSignalReportResource\Pages;

use AIArmada\Signals\Features\Reporting\Api\SavedReportAdministration;
use Illuminate\Database\Eloquent\Model;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Resources\SavedSignalReportResource;
use AIArmada\FilamentSignals\Support\SavedSignalReportMutationGuard;
use Filament\Resources\Pages\EditRecord;

final class EditSavedSignalReport extends EditRecord
{
    protected static string $resource = SavedSignalReportResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(SavedReportAdministration::class)->update((string) $record->getKey(), $data);

        return $record->refresh();
    }

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
