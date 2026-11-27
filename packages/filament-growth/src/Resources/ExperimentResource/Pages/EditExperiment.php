<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\ExperimentResource\Pages;

use AIArmada\FilamentGrowth\Resources\ExperimentResource;
use AIArmada\Growth\Actions\SaveExperiment;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class EditExperiment extends EditRecord
{
    protected static string $resource = ExperimentResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        Gate::authorize('update', $record);

        return app(SaveExperiment::class)->update((string) $record->getKey(), $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
