<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Queries;

use AIArmada\Affiliates\Features\Programs\Api\TrackingProgramGuard;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class LockTrackingProgram implements TrackingProgramGuard
{
    public function assertReference(string $programId, int $connectionIdentity): void
    {
        if (! Str::isUuid($programId)) {
            throw new AuthorizationException('Invalid program reference.');
        }

        $program = new AffiliateProgram;
        if (spl_object_id($program->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Tracking program validation requires a shared database connection.');
        }

        $query = AffiliateProgram::query()->whereKey($programId);

        if ($query->exists()) {
            return;
        }

        if (config('affiliates.owner.enabled', false) && AffiliateProgram::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->whereKey($programId)
            ->whereNull($program->ownerScopeConfig()->ownerTypeColumn)
            ->whereNull($program->ownerScopeConfig()->ownerIdColumn)
            ->exists()) {
            return;
        }

        throw new AuthorizationException('Cross-tenant or missing program reference is not allowed.');
    }

    public function lockForTracking(string $programId, int $connectionIdentity): void
    {
        if (! Str::isUuid($programId)) {
            throw ValidationException::withMessages(['program_id' => 'A valid program ID is required.']);
        }

        $connection = (new AffiliateProgram)->getConnection();
        if (spl_object_id($connection) !== $connectionIdentity || $connection->transactionLevel() === 0) {
            throw new LogicException('Tracking program validation requires a shared active transaction.');
        }

        if (! config('affiliates.owner.enabled', false)) {
            AffiliateProgram::query()->sharedLock()->findOrFail($programId);

            return;
        }

        $message = 'Selected program is not accessible in the current owner scope.';
        $program = OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $programId, includeGlobal: true, message: $message);
        $locked = AffiliateProgram::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->whereKey($programId)
            ->where('owner_type', $program->owner_type)
            ->where('owner_id', $program->owner_id)
            ->sharedLock()
            ->first();

        if ($locked === null) {
            throw new AuthorizationException($message);
        }
    }
}
