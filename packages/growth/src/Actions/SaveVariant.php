<?php

declare(strict_types=1);

namespace AIArmada\Growth\Actions;

use AIArmada\Growth\Queries\ExperimentOwnership;
use AIArmada\Growth\Enums\ExperimentModuleType;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final readonly class SaveVariant
{
    /** @param array<string, mixed> $data */
    public function create(array $data): Variant
    {
        return (new Variant)->getConnection()->transaction(function () use ($data): Variant {
            $experimentId = $data['experiment_id'] ?? null;

            if (! is_string($experimentId)) {
                throw new AuthorizationException('A writable experiment is required.');
            }

            $experiment = ExperimentOwnership::scopeExperiments(Experiment::query(), writable: true)
                ->whereKey($experimentId)->lockForUpdate()->firstOrFail();

            $record = new Variant($this->attributes($data, $experiment));
            $record->experiment_id = (string) $experiment->getKey();
            $record->saveOrFail();

            return $record;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data): Variant
    {
        return (new Variant)->getConnection()->transaction(function () use ($id, $data): Variant {
            $record = ExperimentOwnership::scopeVariants(Variant::query(), writable: true)
                ->whereKey($id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('experiment_id', $data) && $data['experiment_id'] !== $record->experiment_id) {
                throw ValidationException::withMessages(['experiment_id' => 'The parent experiment cannot be changed.']);
            }

            $experiment = ExperimentOwnership::scopeExperiments(Experiment::query(), writable: true)
                ->whereKey($record->experiment_id)->firstOrFail();

            $record->fill($this->attributes($data, $experiment));
            $record->saveOrFail();

            return $record;
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, Experiment $experiment): array
    {
        $data = Arr::only($data, ['code', 'name', 'description', 'traffic_percentage', 'position', 'is_control', 'status', 'settings']);

        if (array_key_exists('settings', $data)) {
            $data['settings'] = $this->normalizeSettings($experiment->module_type, $data['settings']);
        }

        return $data;
    }

    /** @return array<string, mixed>|null */
    public function normalizeSettings(string $moduleType, mixed $settings): ?array
    {
        $allowed = match ($moduleType) {
            ExperimentModuleType::SalesPageTest->value => ['destination_url', 'headline', 'cta_copy'],
            ExperimentModuleType::FunnelTest->value => ['entry_path', 'step_key', 'offer_label'],
            ExperimentModuleType::PricingTest->value => ['price_label', 'price_minor', 'currency'],
            default => [],
        };

        $normalized = Arr::only(is_array($settings) ? $settings : [], $allowed);

        return $normalized === [] ? null : $normalized;
    }
}
