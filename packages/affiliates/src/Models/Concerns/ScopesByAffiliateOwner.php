<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models\Concerns;

use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScopeConfigFactory;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use LogicException;

trait ScopesByAffiliateOwner
{
    protected static string $affiliateOwnerForeignKey = 'affiliate_id';

    protected static function bootScopesByAffiliateOwner(): void
    {
        static::creating(function (Model $model): void {
            static::guardAffiliateOwnerForeignKey($model);
        });

        static::updating(function (Model $model): void {
            static::guardAffiliateOwnerForeignKey($model);
        });

        static::deleting(function (Model $model): void {
            static::guardAffiliateOwnerForeignKey($model);
        });

        static::addGlobalScope('affiliate_owner', function (Builder $builder): void {
            if (! (bool) config('affiliates.owner.enabled', false)) {
                return;
            }

            $builder->whereIn(
                $builder->getModel()->qualifyColumn(static::$affiliateOwnerForeignKey),
                static::visibleAffiliateIds($builder->getModel())
            );
        });
    }

    protected static function visibleAffiliateIds(Model $model): QueryBuilder
    {
        $connection = $model->getConnection();
        $reference = app(MerchantIdentity::class)->ownerTable();

        if ($reference->connectionIdentity !== spl_object_id($connection)) {
            throw new LogicException('Affiliate ownership requires a shared database connection.');
        }
        $config = OwnerScopeConfigFactory::fromConfig('affiliates.owner');
        $owner = OwnerContext::resolveConfigured($config);
        OwnerContext::assertResolvedOrExplicitGlobal($owner);

        return OwnerQuery::applyToQueryBuilder(
            $connection->table($reference->table)->select($reference->keyColumn),
            $owner,
            ! OwnerScopeOverride::suppressIncludeGlobal() && $config->includeGlobal,
            $config->ownerTypeColumn,
            $config->ownerIdColumn,
        );
    }

    protected static function guardAffiliateOwnerForeignKey(Model $model): void
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return;
        }

        $foreignKey = static::$affiliateOwnerForeignKey;
        $affiliateIds = [$model->getAttribute($foreignKey)];

        if ($model->exists) {
            $affiliateIds[] = $model->getOriginal($foreignKey);
        }

        $guard = app(AffiliateAccessGuard::class);

        foreach (array_unique($affiliateIds) as $affiliateId) {
            if ($affiliateId === null) {
                throw new AuthorizationException('An affiliate-owned record requires an affiliate reference.');
            }

            $guard->assertCanWrite((string) $affiliateId, spl_object_id($model->getConnection()));
        }
    }
}
