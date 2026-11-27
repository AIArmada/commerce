<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\ExperimentResource\Pages;

use AIArmada\FilamentGrowth\Actions\SaveExperiment;
use AIArmada\FilamentGrowth\Resources\ExperimentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateExperiment extends CreateRecord
{
    protected static string $resource = ExperimentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveExperiment::class)->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
