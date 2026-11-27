<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Actions;

use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\Growth\Actions\SaveExperiment;
use AIArmada\Growth\Models\Experiment;
use Illuminate\Support\Facades\Gate;

final readonly class SetExperimentRunning
{
    public function handle(Experiment $record, bool $running): bool
    {
        return $record->getConnection()->transaction(function () use ($record, $running): bool {
            $current = Experiment::query()->whereKey($record->getKey())->lockForUpdate()->first();

            if (! $current instanceof Experiment || ! ExperimentAccess::canMutateExperiment($current) || Gate::denies('update', $current)) {
                return false;
            }

            return app(SaveExperiment::class)->setRunning((string) $current->getKey(), $running);
        });
    }
}
