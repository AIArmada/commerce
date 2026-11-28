<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs\Api;

interface RewardProgramLockApi
{
    /** Acquire the scoped program row lock within the caller's transaction. */
    public function lockScopedProgram(string $programId): void;

    public function findPolicy(string $programId, string $versionId): ?RewardPolicySnapshot;
}
