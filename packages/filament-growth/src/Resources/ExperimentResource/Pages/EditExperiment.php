<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\ExperimentResource\Pages;

use AIArmada\FilamentGrowth\Actions\SaveExperiment;
use AIArmada\FilamentGrowth\Resources\ExperimentResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditExperiment extends EditRecord
{
    protected static string $resource = ExperimentResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(SaveExperiment::class)->update((string) $record->getKey(), $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
