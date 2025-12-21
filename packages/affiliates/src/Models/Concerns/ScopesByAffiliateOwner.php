<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models\Concerns;

use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

trait ScopesByAffiliateOwner
{
    protected static string $affiliateOwnerForeignKey = 'affiliate_id';

    protected static function bootScopesByAffiliateOwner(): void
    {
        static::creating(function ($model): void {
            static::guardAffiliateOwnerForeignKey($model);
        });

        static::updating(function ($model): void {
            static::guardAffiliateOwnerForeignKey($model);
        });

        static::addGlobalScope('affiliate_owner', function (Builder $builder): void {
            if (! (bool) config('affiliates.owner.enabled', false)) {
                return;
            }

            $foreignKey = static::$affiliateOwnerForeignKey;

            $builder->whereIn(
                $builder->getModel()->qualifyColumn($foreignKey),
                Affiliate::query()->select('id')
            );
        });
    }

    protected static function guardAffiliateOwnerForeignKey(object $model): void
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return;
        }

        $foreignKey = static::$affiliateOwnerForeignKey;
        $affiliateId = $model->{$foreignKey} ?? null;

        if ($affiliateId === null) {
            return;
        }

        if (! Affiliate::query()->whereKey($affiliateId)->exists()) {
            throw new AuthorizationException('Cross-tenant affiliate reference is not allowed.');
        }
    }
}
