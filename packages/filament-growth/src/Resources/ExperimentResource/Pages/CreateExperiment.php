<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\ExperimentResource\Pages;

use AIArmada\FilamentGrowth\Resources\ExperimentResource;
use AIArmada\Growth\Actions\SaveExperiment;
use AIArmada\Growth\Models\Experiment;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class CreateExperiment extends CreateRecord
{
    protected static string $resource = ExperimentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        Gate::authorize('create', Experiment::class);

        return app(SaveExperiment::class)->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
