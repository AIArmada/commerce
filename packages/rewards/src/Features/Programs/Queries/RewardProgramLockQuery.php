<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs\Queries;

use AIArmada\Rewards\Features\Programs\Api\RewardPolicySnapshot;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;
use AIArmada\Rewards\Features\Programs\Models\RewardProgram;
use AIArmada\Rewards\Features\Programs\Models\RewardProgramVersion;
use AIArmada\Rewards\Support\RewardProgramGuard;
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
