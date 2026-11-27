<?php

declare(strict_types=1);

namespace AIArmada\Growth\Actions;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Growth\Models\Assignment;

final readonly class RecomputeExperimentAssignments
{
    public function __construct(private RepairExperimentAssignment $repair) {}

    /** @return array{changed: int, quarantined: int, unchanged: int} */
    public function handle(bool $dryRun): array
    {
        $runner = new OwnerBatchRunner(Assignment::class, ['enabled' => 'growth.features.owner.enabled']);

        $results = OwnerScopeOverride::withoutIncludeGlobal(fn () => $runner->forEach(function () use ($dryRun): array {
            $counts = ['changed' => 0, 'quarantined' => 0, 'unchanged' => 0];

            Assignment::query()
                ->eachById(function (Assignment $assignment) use (&$counts, $dryRun): void {
                    $result = $this->repair->handle($assignment, ! $dryRun);
                    $counts[$result]++;
                });

            return $counts;
        }));

        return [
            'changed' => (int) collect($results)->sum('changed'),
            'quarantined' => (int) collect($results)->sum('quarantined'),
            'unchanged' => (int) collect($results)->sum('unchanged'),
        ];
    }
}
