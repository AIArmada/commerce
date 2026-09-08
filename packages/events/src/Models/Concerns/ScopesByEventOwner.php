<?php

declare(strict_types=1);

namespace AIArmada\Events\Models\Concerns;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Support\EventSubmissionOwnerScope;
use AIArmada\Events\Support\EventWriteGuard;
use AIArmada\Events\Support\ModelResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * Applies the events owner boundary through an existing event relationship.
 *
 * Event child tables intentionally do not duplicate owner columns. The event
 * root owns the tuple and this trait makes that relationship the query and
 * write boundary for children. Models with direct owner columns use HasOwner.
 */
trait ScopesByEventOwner
{
    public static function bootScopesByEventOwner(): void
    {
        static::addGlobalScope('event_owner', function (Builder $builder): void {
            if (! self::eventOwnerScopeEnabled()) {
                return;
            }

            $owner = OwnerContext::resolve();

            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', $builder->getModel()::class),
            );

            $model = $builder->getModel();
            $morphRelation = static::eventOwnerMorphRelation();

            if ($morphRelation !== null) {
                $builder->whereHasMorph(
                    $morphRelation,
                    '*',
                    function (Builder $relationQuery, string $type) use ($owner): void {
                        $relatedClass = Relation::getMorphedModel($type) ?? $type;

                        if (! is_a($relatedClass, Model::class, true)) {
                            $relationQuery->whereRaw('1 = 0');

                            return;
                        }

                        $related = new $relatedClass;

                        if ($owner instanceof Model
                            && $related->getMorphClass() === $owner->getMorphClass()) {
                            $relationQuery->whereKey($owner->getKey());

                            return;
                        }

                        if (! self::hasOwnerBoundary($relatedClass)) {
                            $relationQuery->whereRaw('1 = 0');
                        }
                    },
                );
            } elseif (static::eventOwnerRelation() !== null) {
                $builder->whereHas(static::eventOwnerRelation());
            }

            $eventIdColumn = static::eventOwnerEventIdColumn();

            if ($eventIdColumn === null) {
                return;
            }

            $eventClass = ModelResolver::eventClass();
            $event = new $eventClass;

            $builder->where(function (Builder $eventQuery) use ($model, $event, $eventClass, $eventIdColumn): void {
                $eventQuery
                    ->whereNull($model->qualifyColumn($eventIdColumn))
                    ->orWhereIn(
                        $model->qualifyColumn($eventIdColumn),
                        $eventClass::query()->select($event->qualifyColumn($event->getKeyName())),
                    );
            });
        });

        static::saving(self::guardEventOwnerWrite(...));
        static::deleting(self::guardEventOwnerWrite(...));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutOwnerScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('event_owner');
    }

    protected static function eventOwnerRelation(): ?string
    {
        return 'event';
    }

    protected static function eventOwnerMorphRelation(): ?string
    {
        return null;
    }

    protected static function eventOwnerEventIdColumn(): ?string
    {
        return null;
    }

    private static function eventOwnerScopeEnabled(): bool
    {
        return (bool) config('events.features.owner.enabled', true);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function hasOwnerBoundary(string $modelClass): bool
    {
        if (method_exists($modelClass, 'ownerScopeConfig')) {
            return $modelClass::ownerScopeConfig()->enabled;
        }

        if (method_exists($modelClass, 'eventOwnerRelation')) {
            return true;
        }

        return EventSubmissionOwnerScope::supports($modelClass);
    }

    private static function guardEventOwnerWrite(Model $model): void
    {
        if (! self::eventOwnerScopeEnabled()) {
            return;
        }

        $eventIdColumn = static::eventOwnerEventIdColumn();
        $eventId = $eventIdColumn === null ? null : $model->getAttribute($eventIdColumn);

        if ($eventId !== null) {
            EventWriteGuard::findOrFail($eventId);
        }

        $morphRelation = static::eventOwnerMorphRelation();

        if ($morphRelation !== null) {
            self::guardMorphOwner($model, $morphRelation);
        }

        $relation = static::eventOwnerRelation();

        if ($relation === null) {
            return;
        }

        if ($relation === 'event' && $eventId !== null) {
            return;
        }

        $related = self::resolveRelationPath($model, $relation);

        if (! $related instanceof Model) {
            throw new AuthorizationException(sprintf(
                'A visible owner-scoped parent is required to write %s records.',
                $model::class,
            ));
        }

        self::assertVisibleOwnerModel($related);
    }

    private static function guardMorphOwner(Model $model, string $relationName): void
    {
        $relation = $model->{$relationName}();

        if (! $relation instanceof MorphTo) {
            throw new InvalidArgumentException(sprintf('%s must be a morphTo relation.', $relationName));
        }

        $typeColumn = $relation->getMorphType();
        $idColumn = $relation->getForeignKeyName();
        $type = $model->getAttribute($typeColumn);
        $id = $model->getAttribute($idColumn);

        if (($type === null) !== ($id === null)) {
            throw new InvalidArgumentException(sprintf(
                '%s type and id must both be present.',
                str($relationName)->headline()->lower()->toString(),
            ));
        }

        if ($type === null || $id === null) {
            throw new AuthorizationException(sprintf(
                'A %s is required to write %s records.',
                str($relationName)->headline()->lower()->toString(),
                $model::class,
            ));
        }

        if ($model->exists
            && ($model->getOriginal($typeColumn) !== $type
                || (string) $model->getOriginal($idColumn) !== (string) $id)) {
            throw new InvalidArgumentException(sprintf(
                '%s ownership cannot be reassigned after creation.',
                str($relationName)->headline()->toString(),
            ));
        }

        $model->unsetRelation($relationName);
        $related = $model->getRelationValue($relationName);

        if (! $related instanceof Model) {
            throw new AuthorizationException(sprintf(
                'A visible %s is required to write %s records.',
                str($relationName)->headline()->lower()->toString(),
                $model::class,
            ));
        }

        self::assertVisibleOwnerModel($related);
    }

    private static function resolveRelationPath(Model $model, string $path): ?Model
    {
        $related = $model;

        foreach (explode('.', $path) as $relationName) {
            $related->unsetRelation($relationName);
            $related = $related->getRelationValue($relationName);

            if (! $related instanceof Model) {
                return null;
            }
        }

        return $related;
    }

    private static function assertVisibleOwnerModel(Model $model): void
    {
        $owner = OwnerContext::resolve();

        if ($owner instanceof Model
            && $model->getMorphClass() === $owner->getMorphClass()
            && (string) $model->getKey() === (string) $owner->getKey()) {
            return;
        }

        $eventClass = ModelResolver::eventClass();

        if ($model instanceof $eventClass) {
            EventWriteGuard::findOrFail($model);

            return;
        }

        $modelClass = $model::class;

        if (method_exists($modelClass, 'ownerScopeConfig')) {
            if (! $modelClass::ownerScopeConfig()->enabled) {
                throw new AuthorizationException(sprintf(
                    '%s does not expose an owner-safe boundary.',
                    $modelClass,
                ));
            }

            OwnerWriteGuard::findOrFailForOwner($modelClass, $model->getKey());

            return;
        }

        if (method_exists($modelClass, 'eventOwnerRelation')
            || EventSubmissionOwnerScope::supports($modelClass)) {
            if (! $modelClass::query()->whereKey($model->getKey())->exists()) {
                throw new AuthorizationException(sprintf(
                    'Cross-owner write blocked for %s.',
                    $modelClass,
                ));
            }

            return;
        }

        throw new AuthorizationException(sprintf(
            '%s does not expose an owner-safe boundary.',
            $modelClass,
        ));
    }
}
