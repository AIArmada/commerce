<?php

declare(strict_types=1);

namespace AIArmada\Growth\Actions;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Experiment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class ArchiveExperiments
{
    public function handle(CarbonImmutable $threshold, bool $dryRun): int
    {
        $runner = new OwnerBatchRunner(Experiment::class, [
            'enabled' => 'growth.features.owner.enabled',
        ]);

        /** @var Collection<int, array{archived: int}> $results */
        $results = OwnerScopeOverride::withoutIncludeGlobal(fn () => $runner->forEach(function () use ($threshold, $dryRun): array {
            $archived = 0;

            Experiment::query()
                ->where('status', ExperimentStatus::Concluded->value)
                ->where('updated_at', '<', $threshold)
                ->chunkById(500, function ($experiments) use (&$archived, $threshold, $dryRun): void {
                    foreach ($experiments as $experiment) {
                        if ($dryRun) {
                            $archived++;

                            continue;
                        }

                        $changed = $experiment->getConnection()->transaction(function () use ($experiment, $threshold): bool {
                            $current = Experiment::query()
                                ->whereKey($experiment->getKey())
                                ->where('status', ExperimentStatus::Concluded->value)
                                ->where('updated_at', '<', $threshold)
                                ->lockForUpdate()
                                ->first();

                            return $current !== null && $current->transitionTo(ExperimentStatus::Archived)->save();
                        });

                        $archived += (int) $changed;
                    }
                });

            return ['archived' => $archived];
        }));

        return (int) $results->sum('archived');
    }
}
