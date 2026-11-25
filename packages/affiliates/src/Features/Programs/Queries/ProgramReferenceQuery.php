<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Queries;

use AIArmada\Affiliates\Features\Programs\Api\ProgramReferenceReader;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final readonly class ProgramReferenceQuery implements ProgramReferenceReader
{
    public function exists(string $id): bool
    {
        if (! Str::isUuid($id)) {
            return false;
        }

        if (! config('affiliates.owner.enabled', false)) {
            return AffiliateProgram::query()->whereKey($id)->exists();
        }

        try {
            OwnerWriteGuard::findOrFailForOwner(
                AffiliateProgram::class,
                $id,
                includeGlobal: (bool) config('affiliates.owner.include_global', false),
            );

            return true;
        } catch (AuthorizationException | InvalidArgumentException | RuntimeException) {
            return false;
        }
    }
}
