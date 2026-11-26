<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models\Concerns;

use AIArmada\Affiliates\Features\Programs\Api\ProgramReferenceReader;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScopeConfigFactory;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Database-level owner projection for program-derived records.
 * Authorization of writes belongs to Programs; filtering uses a correlated
 * table subquery instead of exposing a foreign Eloquent model or loading IDs.
 */
trait ScopesByProgramOwner
{
    protected static string $programOwnerForeignKey = 'program_id';

    protected static function bootScopesByProgramOwner(): void
    {
        static::creating(function (Model $model): void {
            static::guardProgramOwnerForeignKey($model);
        });

        static::updating(function (Model $model): void {
            static::guardProgramOwnerForeignKey($model);
        });

        static::deleting(function (Model $model): void {
            static::guardProgramOwnerForeignKey($model);
        });

        static::addGlobalScope('program_owner', function (Builder $builder): void {
            if (! (bool) config('affiliates.owner.enabled', false)) {
                return;
            }

            $builder->whereIn(
                $builder->getModel()->qualifyColumn(static::$programOwnerForeignKey),
                static::visibleProgramIds($builder->getModel())
            );
        });
    }

    public function scopeForGlobalProgramConfiguration(Builder $query): Builder
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return $query;
        }

        if (! OwnerContext::isExplicitGlobal()) {
            throw new AuthorizationException('Global program configuration requires explicit global context.');
        }

        $foreignKey = $query->getModel()->qualifyColumn(static::$programOwnerForeignKey);
        $programIds = static::visibleProgramIds($query->getModel());

        return $query->withoutGlobalScope('program_owner')->where(static function (Builder $query) use ($foreignKey, $programIds): void {
            $query->whereNull($foreignKey)->orWhereIn($foreignKey, $programIds);
        });
    }

    protected static function visibleProgramIds(Model $model): QueryBuilder
    {
        $connection = $model->getConnection();
        app(ProgramReferenceReader::class)->assertSharedConnection(spl_object_id($connection));
        $config = OwnerScopeConfigFactory::fromConfig('affiliates.owner');
        $owner = OwnerContext::resolveConfigured($config);
        OwnerContext::assertResolvedOrExplicitGlobal($owner);

        return OwnerQuery::applyToQueryBuilder(
            $connection->table((string) config('affiliates.database.tables.programs', 'affiliate_programs'))->select('id'),
            $owner,
            ! OwnerScopeOverride::suppressIncludeGlobal() && $config->includeGlobal,
            $config->ownerTypeColumn,
            $config->ownerIdColumn,
        );
    }

    protected static function guardProgramOwnerForeignKey(Model $model): void
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return;
        }

        $foreignKey = static::$programOwnerForeignKey;
        $programIds = [$model->getAttribute($foreignKey)];

        if ($model->exists) {
            $programIds[] = $model->getOriginal($foreignKey);
        }

        $reader = app(ProgramReferenceReader::class);

        foreach (array_unique($programIds) as $programId) {
            if ($programId === null) {
                if (! OwnerContext::isExplicitGlobal()) {
                    throw new AuthorizationException('Global program configuration requires explicit global context.');
                }

                continue;
            }

            $reader->assertWritable((string) $programId, spl_object_id($model->getConnection()));
        }
    }
}
