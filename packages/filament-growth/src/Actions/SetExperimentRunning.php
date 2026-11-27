<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Actions;

use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\Growth\Enums\ExperimentStatus;
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

            if (in_array($current->status, [ExperimentStatus::Concluded, ExperimentStatus::Archived], true)) {
                return false;
            }

            $status = $running ? ExperimentStatus::Active : ExperimentStatus::Paused;

            if ($current->status !== $status) {
                $current->transitionTo($status)->saveOrFail();
            }

            return $current->status === ExperimentStatus::Active;
        });
    }
}
