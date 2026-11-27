<?php

declare(strict_types=1);

namespace AIArmada\Growth\Actions;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Growth\Models\Assignment;
use Illuminate\Support\Collection;

final readonly class RecomputeExperimentAssignments
{
    public function __construct(private RepairExperimentAssignment $repair) {}

    /** @return array{changed: int, quarantined: int, unchanged: int} */
    public function handle(bool $dryRun): array
    {
        $runner = new OwnerBatchRunner(Assignment::class, ['enabled' => 'growth.features.owner.enabled']);

        /** @var Collection<int, array{changed: int, quarantined: int, unchanged: int}> $results */
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
            'changed' => (int) $results->sum('changed'),
            'quarantined' => (int) $results->sum('quarantined'),
            'unchanged' => (int) $results->sum('unchanged'),
        ];
    }
}
