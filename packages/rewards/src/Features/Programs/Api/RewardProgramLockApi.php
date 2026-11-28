<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs\Api;

interface RewardProgramLockApi
{
    public function assertProgramInScope(string $programId): void;

    public function assertProgramInScopeWhenExists(string $programId): void;

    /** Run system work under the program's owner, restoring the previous context. */
    public function withProgramOwner(string $programId, callable $callback): mixed;

    /** Acquire the scoped program row lock within the caller's transaction. */
    public function lockScopedProgram(string $programId): void;

    public function findPolicy(string $programId, string $versionId): ?RewardPolicySnapshot;

    /** A locking read requires the caller's transaction. */
    public function readProgram(string $programId, bool $lock = false): RewardProgramSnapshot;

    public function effectivePolicy(string $programId, string $at): ?RewardPolicySnapshot;

    /** @return list<array{program: RewardProgramSnapshot, policy: RewardPolicySnapshot}> */
    public function activePolicies(string $at, bool $systemEnumeration = false): array;
}
