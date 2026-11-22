<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\Models\RewardProgramVersion;
use Carbon\CarbonImmutable;

/**
 * Pure effective-timeline resolution. Version windows derive from the next
 * version's effective_from; there is no mutable effective_to anywhere.
 */
final class RewardVersionResolver
{
    public static function resolveCurrentForProgram(RewardProgram $program, ?CarbonImmutable $at = null): ?RewardProgramVersion
    {
        $versions = RewardProgramVersion::query()
            ->where('program_id', $program->getKey())
            ->orderBy('version')
            ->get();

        $timeline = [];

        foreach ($versions as $version) {
            $timeline[] = [
                'id' => (string) $version->getKey(),
                'version' => (int) $version->version,
                'effective_from' => CarbonImmutable::parse($version->effective_from)->setTimezone('UTC'),
            ];
        }

        $applicable = self::resolveAt($timeline, ($at ?? CarbonImmutable::now('UTC'))->setTimezone('UTC'));

        if ($applicable === null) {
            return null;
        }

        foreach ($versions as $version) {
            if ((string) $version->getKey() === $applicable['id']) {
                return $version;
            }
        }

        return null;
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
