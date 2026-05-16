<?php

declare(strict_types=1);

namespace AIArmada\Growth\Actions;

use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use InvalidArgumentException;

final class BuildExperimentSignalProperties
{
    /**
     * @return array<string, string>
     */
    public function handle(Assignment $assignment): array
    {
        return $this->contextForAssignment($assignment);
    }

    /**
     * @return array<string, string>
     */
    public function contextForAssignment(Assignment $assignment): array
    {
        $experiment = $this->resolveExperiment($assignment);
        $variant = $this->resolveVariant($assignment);

        if (! $experiment instanceof Experiment || ! $variant instanceof Variant) {
            throw new InvalidArgumentException('Assignment experiment context could not be resolved.');
        }

        return [
            'experiment_id' => (string) $assignment->experiment_id,
            'experiment_slug' => (string) $experiment->slug,
            'variant_id' => (string) $assignment->variant_id,
            'variant_code' => (string) $variant->code,
            'assignment_id' => (string) $assignment->getKey(),
            'module_type' => (string) $experiment->module_type,
        ];
    }

    /**
     * @param  iterable<Assignment>  $assignments
     * @return list<array<string, string>>
     */
    public function contextsForAssignments(iterable $assignments): array
    {
        $contexts = [];

        foreach ($assignments as $assignment) {
            if (! $assignment instanceof Assignment) {
                continue;
            }

            $contexts[] = $this->contextForAssignment($assignment);
        }

        return $contexts;
    }

    private function resolveExperiment(Assignment $assignment): ?Experiment
    {
        if ($assignment->relationLoaded('experiment') && $assignment->experiment instanceof Experiment) {
            return $assignment->experiment;
        }

        $experiment = Experiment::query()
            ->withoutOwnerScope()
            ->whereKey($assignment->experiment_id)
            ->first();

        return $experiment instanceof Experiment ? $experiment : null;
    }

    private function resolveVariant(Assignment $assignment): ?Variant
    {
        if ($assignment->relationLoaded('variant') && $assignment->variant instanceof Variant) {
            return $assignment->variant;
        }

        $variant = Variant::query()
            ->withoutOwnerScope()
            ->whereKey($assignment->variant_id)
            ->first();

        return $variant instanceof Variant ? $variant : null;
    }
}
