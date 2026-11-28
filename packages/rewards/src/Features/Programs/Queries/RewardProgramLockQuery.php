<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs\Queries;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Rewards\Data\RewardProgramStatus;
use AIArmada\Rewards\Features\Programs\Api\RewardPolicySnapshot;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramSnapshot;
use AIArmada\Rewards\Features\Programs\Models\RewardProgram;
use AIArmada\Rewards\Features\Programs\Models\RewardProgramVersion;
use AIArmada\Rewards\Features\Programs\Persistence\RewardProgramGuard;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class RewardProgramLockQuery implements RewardProgramLockApi
{
    public function assertProgramInScope(string $programId): void
    {
        RewardProgramGuard::findProgramOrFail($programId);
    }

    public function assertProgramInScopeWhenExists(string $programId): void
    {
        RewardProgramGuard::assertProgramInScopeWhenExists($programId);
    }

    public function withProgramOwner(string $programId, callable $callback): mixed
    {
        return RewardProgramGuard::withProgramOwner($programId, $callback);
    }

    public function withProgramOwnerWhenExists(string $programId, callable $callback): mixed
    {
        if (! RewardProgram::query()->withoutGlobalScopes()->whereKey($programId)->exists()) {
            return $callback();
        }

        return $this->withProgramOwner($programId, $callback);
    }

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

        return $this->effectivePolicyForProgram($programId, CarbonImmutable::parse($at)->setTimezone('UTC'));
    }

    public function activePolicies(string $at, bool $systemEnumeration = false): array
    {
        $decision = CarbonImmutable::parse($at)->setTimezone('UTC');
        $query = RewardProgram::query();

        if ($systemEnumeration && config('rewards.owner.enabled', true)
            && ! OwnerContext::hasOverride() && OwnerContext::resolve() === null) {
            $query->withoutGlobalScopes();
        }

        $programKey = $query->getModel()->qualifyColumn('id');
        $effectiveVersion = RewardProgramVersion::query()
            ->select('id')
            ->whereColumn('program_id', $programKey)
            ->where('effective_from', '<=', $decision)
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->limit(1);

        $programs = $query->where('status', RewardProgramStatus::Active->value)
            ->orderBy('family_key')
            ->orderBy('id')
            ->select(['id', 'family_key', 'status'])
            ->addSelect(['effective_policy_id' => $effectiveVersion])
            ->get();
        $result = [];

        foreach ($programs->chunk(200) as $batch) {
            $versionIds = $batch->pluck('effective_policy_id')->filter()->all();
            $versions = RewardProgramVersion::query()->whereKey($versionIds)->get()->keyBy('id');

            foreach ($batch as $program) {
                $versionId = $program->getAttribute('effective_policy_id');
                $version = is_string($versionId) ? $versions->get($versionId) : null;

                if ($version instanceof RewardProgramVersion) {
                    $result[] = [
                        'program' => new RewardProgramSnapshot((string) $program->getKey(), $program->family_key, $program->status->value),
                        'policy' => $this->snapshot($version),
                    ];
                }
            }
        }

        return $result;
    }

    private function effectivePolicyForProgram(string $programId, CarbonImmutable $at): ?RewardPolicySnapshot
    {
        $version = RewardProgramVersion::query()
            ->where('program_id', $programId)
            ->where('effective_from', '<=', $at)
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
