<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs\Queries;

use AIArmada\Rewards\Features\Programs\Models\RewardProgram;
use AIArmada\Rewards\Features\Programs\Models\RewardProgramVersion;
use Carbon\CarbonImmutable;

/**
 * Pure effective-timeline resolution. Version windows derive from the next
 * version's effective_from; there is no mutable effective_to anywhere.
 */
final class RewardVersionResolver
{
    public static function resolveCurrentForProgram(RewardProgram $program, ?CarbonImmutable $at = null): ?RewardProgramVersion
    {
        return RewardProgramVersion::query()
            ->where('program_id', $program->getKey())
            ->where('effective_from', '<=', ($at ?? CarbonImmutable::now('UTC'))->setTimezone('UTC'))
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @param  list<array{id: string, version: int, effective_from: CarbonImmutable}>  $versions
     * @return array{id: string, version: int, effective_from: CarbonImmutable}|null
     */
    public static function resolveAt(array $versions, CarbonImmutable $eligibilityTime): ?array
    {
        $sorted = $versions;

        usort($sorted, static function (array $a, array $b): int {
            $byTime = $a['effective_from']->getTimestamp() <=> $b['effective_from']->getTimestamp();

            return $byTime !== 0 ? $byTime : $a['version'] <=> $b['version'];
        });

        $applicable = null;

        foreach ($sorted as $version) {
            if ($version['effective_from']->greaterThan($eligibilityTime)) {
                break;
            }

            $applicable = $version;
        }

        return $applicable;
    }

    /**
     * @param  list<array{id: string, version: int, effective_from: CarbonImmutable}>  $versions
     */
    public static function windowEndFor(array $versions, string $versionId): ?CarbonImmutable
    {
        $sorted = $versions;

        usort($sorted, static function (array $a, array $b): int {
            $byTime = $a['effective_from']->getTimestamp() <=> $b['effective_from']->getTimestamp();

            return $byTime !== 0 ? $byTime : $a['version'] <=> $b['version'];
        });

        $found = false;

        foreach ($sorted as $version) {
            if ($found) {
                return $version['effective_from'];
            }

            if ($version['id'] === $versionId) {
                $found = true;
            }
        }

        return null;
    }
}
