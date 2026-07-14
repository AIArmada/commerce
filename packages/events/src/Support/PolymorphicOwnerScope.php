<?php

declare(strict_types=1);

namespace AIArmada\Events\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Scope;
use InvalidArgumentException;

final class PolymorphicOwnerScope implements Scope
{
    public function __construct(
        private readonly string $relation,
        private readonly ?string $eventIdColumn = null,
    ) {}

    public function apply(Builder $builder, Model $model): void
    {
        if (! self::enabled()) {
            return;
        }

        $owner = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            sprintf('%s requires an owner context or explicit global context.', $model::class),
        );

        $builder->whereHasMorph(
            $this->relation,
            '*',
            function (Builder $relationQuery, string $type) use ($owner): void {
                $modelClass = Relation::getMorphedModel($type) ?? $type;

                if (! is_a($modelClass, Model::class, true)) {
                    $relationQuery->whereRaw('1 = 0');

                    return;
                }

                $related = new $modelClass;

                if ($owner instanceof Model
                    && $related->getMorphClass() === $owner->getMorphClass()) {
                    $relationQuery
                        ->withoutGlobalScope(OwnerScope::class)
                        ->withoutGlobalScope(EventOwnerScope::class)
                        ->withoutGlobalScope(EventSubmissionOwnerScope::class)
                        ->whereKey($owner->getKey());

                    return;
                }

                if (! EventTenantBoundary::hasEnforcedBoundary($modelClass)) {
                    $relationQuery->whereRaw('1 = 0');
                }
            },
        );

        if ($this->eventIdColumn !== null) {
            $eventClass = ModelResolver::eventClass();
            $event = new $eventClass;

            $builder->where(function (Builder $eventQuery) use ($model, $event, $eventClass): void {
                $eventQuery
                    ->whereNull($model->qualifyColumn($this->eventIdColumn))
                    ->orWhereIn(
                        $model->qualifyColumn($this->eventIdColumn),
                        $eventClass::query()->select(
                            $event->qualifyColumn($event->getKeyName()),
                        ),
                    );
            });
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function register(
        string $modelClass,
        string $relation,
        ?string $eventIdColumn = null,
    ): void {
        $modelClass::addGlobalScope(new self($relation, $eventIdColumn));

        $guard = static function (Model $model) use ($relation, $eventIdColumn): void {
            self::guardWrite($model, $relation, $eventIdColumn);
        };

        $modelClass::saving($guard);
        $modelClass::deleting($guard);
    }

    private static function guardWrite(
        Model $model,
        string $relation,
        ?string $eventIdColumn,
    ): void {
        if (! self::enabled()) {
            return;
        }

        if ($eventIdColumn !== null && $model->getAttribute($eventIdColumn) !== null) {
            EventWriteGuard::findOrFail($model->getAttribute($eventIdColumn));
        }

        $relationInstance = $model->{$relation}();
        $typeColumn = $relationInstance->getMorphType();
        $idColumn = $relationInstance->getForeignKeyName();
        $type = $model->getAttribute($typeColumn);
        $id = $model->getAttribute($idColumn);

        if (($type === null) !== ($id === null)) {
            throw new InvalidArgumentException(sprintf(
                '%s type and id must both be present.',
                str($relation)->headline()->lower()->toString(),
            ));
        }

        if ($type === null || $id === null) {
            throw new AuthorizationException(sprintf(
                'A %s is required to write %s records.',
                str($relation)->headline()->lower()->toString(),
                $model::class,
            ));
        }

        if ($model->exists
            && ($model->getOriginal($typeColumn) !== $type
                || (string) $model->getOriginal($idColumn) !== (string) $id)) {
            throw new InvalidArgumentException(sprintf(
                '%s ownership cannot be reassigned after creation.',
                str($relation)->headline()->toString(),
            ));
        }

        $model->unsetRelation($relation);
        $related = $model->getRelationValue($relation);

        if (! $related instanceof Model) {
            throw new AuthorizationException(sprintf(
                'A visible %s is required to write %s records.',
                str($relation)->headline()->lower()->toString(),
                $model::class,
            ));
        }

        EventTenantBoundary::assertWritable($related);
    }

    private static function enabled(): bool
    {
        return (bool) config('events.features.owner.enabled', true);
    }
}
