<?php

declare(strict_types=1);

namespace AIArmada\Growth\Actions;

use AIArmada\Growth\Queries\ExperimentOwnership;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SaveExperiment
{
    /** @param array<string, mixed> $data */
    public function create(array $data): Experiment
    {
        return (new Experiment)->getConnection()->transaction(function () use ($data): Experiment {
            $propertyId = $data['tracked_property_id'] ?? null;

            if (! is_string($propertyId) || ! (ExperimentOwnership::scopeOwner(TrackedProperty::query(), writable: true)->whereKey($propertyId)->lockForUpdate()->first() instanceof TrackedProperty)) {
                throw new AuthorizationException('Tracked property is not writable in the current owner scope.');
            }

            $record = new Experiment($this->attributes($data));
            $record->tracked_property_id = $propertyId;

            if (! is_string($record->slug) || $record->slug === '') {
                $record->slug = Str::slug((string) ($data['name'] ?? 'experiment'));
            }

            if (isset($data['status'])) {
                $record->transitionTo(($data['status'] instanceof ExperimentStatus ? $data['status'] : ExperimentStatus::from((string) $data['status'])));
            }

            $record->saveOrFail();

            return $record;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data): Experiment
    {
        return (new Experiment)->getConnection()->transaction(function () use ($id, $data): Experiment {
            $record = ExperimentOwnership::scopeExperiments(Experiment::query(), writable: true)
                ->whereKey($id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('tracked_property_id', $data) && $data['tracked_property_id'] !== $record->tracked_property_id) {
                throw ValidationException::withMessages(['tracked_property_id' => 'The tracked property cannot be changed.']);
            }

            $record->fill($this->attributes($data));

            if (isset($data['status'])) {
                $status = ($data['status'] instanceof ExperimentStatus ? $data['status'] : ExperimentStatus::from((string) $data['status']));

                if (($record->status === ExperimentStatus::Archived && $status !== ExperimentStatus::Archived)
                    || ($record->status === ExperimentStatus::Concluded && ! in_array($status, [ExperimentStatus::Concluded, ExperimentStatus::Archived], true))) {
                    throw ValidationException::withMessages(['status' => 'A concluded or archived experiment cannot be reopened.']);
                }

                if ($record->status !== $status) {
                    $record->transitionTo($status);
                }
            }

            $record->saveOrFail();

            return $record;
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return Arr::only($data, [
            'name', 'slug', 'description', 'module_type', 'goal_event_name',
            'goal_event_category', 'winner_metric', 'audience', 'settings',
        ]);
    }
}
