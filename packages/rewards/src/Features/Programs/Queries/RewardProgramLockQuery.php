<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs\Queries;

use AIArmada\Rewards\Features\Programs\Api\RewardPolicySnapshot;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramSnapshot;
use AIArmada\Rewards\Features\Programs\Models\RewardProgram;
use AIArmada\Rewards\Features\Programs\Models\RewardProgramVersion;
use AIArmada\Rewards\Support\RewardProgramGuard;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class RewardProgramLockQuery implements RewardProgramLockApi
{
    public function lockScopedProgram(string $programId): void
    {
        RewardProgramGuard::findProgramOrFail($programId);

        if (! RewardProgram::query()->whereKey($programId)->lockForUpdate()->first(['id']) instanceof RewardProgram) {
            throw new InvalidArgumentException('Unknown reward program.');
        }
    }

    public function findPolicy(string $programId, string $versionId): ?RewardPolicySnapshot
    {
        RewardProgramGuard::findProgramOrFail($programId);

        $version = RewardProgramVersion::query()
            ->where('program_id', $programId)
            ->whereKey($versionId)
            ->first();

        if ($version === null) {
            return null;
        }

        return $this->snapshot($version);
    }

    public function readProgram(string $programId, bool $lock = false): RewardProgramSnapshot
    {
        RewardProgramGuard::findProgramOrFail($programId);

        $query = RewardProgram::query()->whereKey($programId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $program = $query->first(['id', 'family_key', 'status']);

        if ($program === null) {
            throw new InvalidArgumentException('Unknown reward program.');
        }

        return new RewardProgramSnapshot((string) $program->getKey(), $program->family_key, $program->status->value);
    }

    public function effectivePolicy(string $programId, string $at): ?RewardPolicySnapshot
    {
        RewardProgramGuard::findProgramOrFail($programId);

        $version = RewardProgramVersion::query()
            ->where('program_id', $programId)
            ->where('effective_from', '<=', CarbonImmutable::parse($at)->setTimezone('UTC'))
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->first();

        return $version === null ? null : $this->snapshot($version);
    }

    private function snapshot(RewardProgramVersion $version): RewardPolicySnapshot
    {
        return new RewardPolicySnapshot(
            id: (string) $version->getKey(),
            version: $version->version,
            materialTermsHash: $version->material_terms_hash,
            rules: $version->rules,
            terms: $version->terms,
            publishedAt: $version->published_at->toIso8601String(),
            effectiveFrom: $version->effective_from->toIso8601String(),
            compatibleWith: array_values(array_filter($version->compatible_with, is_string(...))),
        );
    }
}
