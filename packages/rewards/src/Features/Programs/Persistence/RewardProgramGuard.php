<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs\Persistence;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Rewards\Features\Programs\Models\RewardProgram;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * Program-boundary write guard: the program is the tenant boundary, and
 * every ledger read/write resolves it first. Ledger tables carry no owner
 * columns; tenancy flows program -> enrollment/award joins, so inbound
 * program and award IDs are validated against the current owner scope
 * before any attach or update.
 */
final class RewardProgramGuard
{
    /**
     * Resolve the program for the current owner scope, including global
     * (platform) programs. Unknown ids throw for a missing program;
     * existing programs outside the scope throw for the scope. When
     * owner scoping is disabled the guard degrades to a plain lookup:
     * single-tenant hosts store owner tuples as data without enforcement.
     */
    public static function findProgramOrFail(RewardProgram | string $program): RewardProgram
    {
        $id = $program instanceof RewardProgram ? (string) $program->getKey() : $program;

        if (! config('rewards.owner.enabled', true)) {
            $plain = RewardProgram::query()->whereKey($id)->first();

            if (! $plain instanceof RewardProgram) {
                throw new InvalidArgumentException('Unknown reward program.');
            }

            return $plain;
        }

        if (RewardProgram::query()->withoutGlobalScopes()->whereKey($id)->doesntExist()) {
            throw new InvalidArgumentException('Unknown reward program.');
        }

        $resolved = OwnerWriteGuard::findOrFailForOwner(RewardProgram::class, $id, includeGlobal: true);

        assert($resolved instanceof RewardProgram);

        return $resolved;
    }

    /**
     * Run a system-context unit of work under the program's own owner.
     *
     * Jobs, sweeps, and commands run without an ambient owner (queue
     * workers, cron) yet must act on every active program. This helper
     * resolves the program row ignoring the ambient scope, enters the
     * program's own owner context — or an explicit-global context for
     * platform programs with a null owner tuple — and restores the
     * previous context afterwards. Ownership stays the security boundary:
     * the callback still sees guarded reads/writes, now under the correct
     * owner instead of a fail-open ambient scope.
     *
     * An unknown program id throws InvalidArgumentException; a program
     * whose owner row was deleted throws InvalidArgumentException naming
     * the orphaned tuple, so queue retries/dead-letter surface the
     * corruption instead of silently skipping the work.
     */
    public static function withProgramOwner(RewardProgram | string $program, callable $callback): mixed
    {
        if (! config('rewards.owner.enabled', true)) {
            return $callback();
        }

        $id = $program instanceof RewardProgram ? (string) $program->getKey() : $program;

        $resolved = RewardProgram::query()->withoutGlobalScopes()->whereKey($id)->first();

        if (! $resolved instanceof RewardProgram) {
            throw new InvalidArgumentException('Unknown reward program.');
        }

        try {
            $owner = OwnerContext::fromTypeAndIdOrFail($resolved->owner_type, $resolved->owner_id);
        } catch (ModelNotFoundException) {
            throw new InvalidArgumentException(sprintf('Reward program "%s" references a deleted owner.', $id));
        }

        return OwnerContext::withOwner($owner, $callback);
    }

    /**
     * Scope-check a program attach reference only when the row exists.
     * Dangling references stay allowed for append-only annotations.
     */
    public static function assertProgramInScopeWhenExists(string $programId): void
    {
        if (RewardProgram::query()->withoutGlobalScopes()->whereKey($programId)->exists()) {
            self::findProgramOrFail($programId);
        }
    }
}
