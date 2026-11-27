<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Actions;

use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\Growth\Actions\SaveExperiment;
use AIArmada\Growth\Models\Experiment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final readonly class DeleteSelectedExperiments
{
    /** @param Collection<int|string, Experiment> $records */
    public function handle(Collection $records): void
    {
        (new Experiment)->getConnection()->transaction(function () use ($records): void {
            foreach ($records as $record) {
                if (! $record instanceof Experiment || ! ExperimentAccess::canMutateExperiment($record)) {
                    throw new RuntimeException('Global growth experiments can only be deleted from explicit global context.');
                }
            }

            Gate::authorize('deleteAny', Experiment::class);

            foreach ($records as $record) {
                Gate::authorize('delete', $record);
            }

            app(SaveExperiment::class)->deleteMany($records->map(static fn (Experiment $record): string => (string) $record->getKey())->values()->all());
        });
    }
}
