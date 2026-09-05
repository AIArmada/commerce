<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

final class TicketingOwnerGuard
{
    /**
     * @param  list<array{relation: string, required?: bool}>  $relations
     */
    public static function assertRelations(Model $model, array $relations): void
    {
        if (! config('ticketing.features.owner.enabled', true)) {
            return;
        }

        $owner = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            sprintf('%s requires an owner context or explicit global context.', $model::class),
        );

        foreach ($relations as $definition) {
            self::assertRelation($model, $definition['relation'], $definition['required'] ?? false);
        }
    }

    private static function assertRelation(Model $model, string $relationName, bool $required): void
    {
        $morphPrefix = Str::snake($relationName);
        $morphType = $model->getAttribute("{$morphPrefix}_type");

        if (array_key_exists("{$morphPrefix}_type", $model->getAttributes())) {
            self::assertMorphRelation($model, $relationName, $morphType, $model->getAttribute("{$morphPrefix}_id"), $required);

            return;
        }

        $relation = $model->{$relationName}();

        if (! $relation instanceof BelongsTo) {
            throw new AuthorizationException(sprintf(
                'Ticketing owner validation requires a BelongsTo or MorphTo relation: %s::%s.',
                $model::class,
                $relationName,
            ));
        }

        $relatedClass = $relation->getRelated()::class;
        $relatedId = null;

        if ($relation instanceof MorphTo) {
            $morphType = $model->getAttribute($relation->getMorphType());
            $relatedId = $model->getAttribute($relation->getForeignKeyName());

            self::assertMorphRelation($model, $relationName, $morphType, $relatedId, $required);

            return;
        }
        $relatedId = $model->getAttribute($relation->getForeignKeyName());

        if ($relatedId === null) {
            if ($required) {
                self::throwMissingRelation($model, $relationName);
            }

            return;
        }

        if (! self::isOwnerScopedModel($relatedClass)) {
            return;
        }

        /** @var class-string<Model> $relatedClass */
        $related = $relatedClass::query()->whereKey($relatedId)->first();

        if ($related instanceof Model) {
            return;
        }

        throw new AuthorizationException(sprintf(
            'The %s relation for %s is not accessible in the current owner scope.',
            $relationName,
            $model::class,
        ));
    }

    private static function assertMorphRelation(Model $model, string $relationName, mixed $morphType, mixed $relatedId, bool $required): void
    {
        if ($morphType === null || $relatedId === null) {
            if ($required) {
                self::throwMissingRelation($model, $relationName);
            }

            return;
        }

        $relatedClass = Relation::getMorphedModel((string) $morphType) ?? (string) $morphType;

        if (! class_exists($relatedClass) || ! is_a($relatedClass, Model::class, true)) {
            return;
        }

        if (! self::isOwnerScopedModel($relatedClass)) {
            return;
        }

        /** @var class-string<Model> $relatedClass */
        if ($relatedClass::query()->whereKey($relatedId)->exists()) {
            return;
        }

        throw new AuthorizationException(sprintf(
            'The %s relation for %s is not accessible in the current owner scope.',
            $relationName,
            $model::class,
        ));
    }

    /**
     * @param  class-string<Model>  $relatedClass
     */
    private static function isOwnerScopedModel(string $relatedClass): bool
    {
        if (in_array(HasOwner::class, class_uses_recursive($relatedClass), true)) {
            return true;
        }

        $eventOwnerScope = 'AIArmada\\Events\\Support\\EventOwnerScope';

        return class_exists($eventOwnerScope)
            && is_callable([$eventOwnerScope, 'supports'])
            && (bool) call_user_func([$eventOwnerScope, 'supports'], $relatedClass);
    }

    private static function throwMissingRelation(Model $model, string $relationName): never
    {
        throw new AuthorizationException(sprintf(
            'A %s relation is required to write %s records.',
            $relationName,
            $model::class,
        ));
    }
}
