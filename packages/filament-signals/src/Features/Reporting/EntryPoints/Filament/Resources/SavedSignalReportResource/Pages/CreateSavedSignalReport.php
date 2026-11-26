<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Resources\SavedSignalReportResource\Pages;

use AIArmada\Signals\Features\Reporting\Api\SavedReportAdministration;
use AIArmada\Signals\Features\Reporting\Models\SavedSignalReport;
use Illuminate\Database\Eloquent\Model;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Resources\SavedSignalReportResource;
use AIArmada\FilamentSignals\Features\Reporting\Support\SavedSignalReportMutationGuard;
use Filament\Resources\Pages\CreateRecord;

final class CreateSavedSignalReport extends CreateRecord
{
    protected static string $resource = SavedSignalReportResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $id = app(SavedReportAdministration::class)->create($data);

        return SavedSignalReport::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(SavedSignalReportMutationGuard::class)->sanitize($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
