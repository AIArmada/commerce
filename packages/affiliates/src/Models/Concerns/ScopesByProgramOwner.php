<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models\Concerns;

use AIArmada\Affiliates\Models\AffiliateProgram;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Provides owner scoping through the program relationship.
 *
 * Use this trait for models that belong to an AffiliateProgram and should
 * inherit the program's owner boundary (e.g., commission rules, promotions, volume tiers).
 */
trait ScopesByProgramOwner
{
    protected static string $programOwnerForeignKey = 'program_id';

    protected static function bootScopesByProgramOwner(): void
    {
        static::creating(function ($model): void {
            static::guardProgramOwnerForeignKey($model);
        });

        static::updating(function ($model): void {
            static::guardProgramOwnerForeignKey($model);
        });

        static::addGlobalScope('program_owner', function (Builder $builder): void {
            if (! (bool) config('affiliates.owner.enabled', false)) {
                return;
            }

            $foreignKey = static::$programOwnerForeignKey;

            $builder->whereIn(
                $builder->getModel()->qualifyColumn($foreignKey),
                AffiliateProgram::query()->select('id')
            );
        });
    }

    protected static function guardProgramOwnerForeignKey(object $model): void
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return;
        }

        $foreignKey = static::$programOwnerForeignKey;
        $programId = $model->{$foreignKey} ?? null;

        if ($programId === null) {
            return;
        }

        if (! AffiliateProgram::query()->whereKey($programId)->exists()) {
            throw new AuthorizationException('Cross-tenant program reference is not allowed.');
        }
    }
}
