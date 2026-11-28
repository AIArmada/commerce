<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Support;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

final class TicketingOwnerGuard
{
    /**
     * @param  list<array{relation: string, required?: bool}>  $relations
     */
    public static function assertRelations(Model $model, array $relations): void
    {
        $config = self::ownerScopeConfig($model);

        if ($config->enabled) {
            $owner = OwnerContext::resolveConfigured($config);

            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', $model::class),
            );
        }

        foreach ($relations as $definition) {
            self::assertRelation($model, $definition['relation'], $definition['required'] ?? false);
        }
    }

    private static function assertRelation(Model $model, string $relationName, bool $required): void
    {
        $relation = $model->{$relationName}();

        if (! $relation instanceof BelongsTo) {
            throw new AuthorizationException(sprintf(
                'Ticketing owner validation requires a BelongsTo or MorphTo relation: %s::%s.',
                $model::class,
                $relationName,
            ));
        }

        if ($relation instanceof MorphTo) {
            self::assertMorphRelation(
                $model,
                $relationName,
                $model->getAttribute($relation->getMorphType()),
                $model->getAttribute($relation->getForeignKeyName()),
                $required,
            );

            return;
        }

        $relatedId = $model->getAttribute($relation->getForeignKeyName());

        if ($relatedId === null || $relatedId === '') {
            if ($required) {
                self::throwMissingRelation($model, $relationName);
            }

            return;
        }

        $relatedClass = $relation->getRelated()::class;
        self::assertAccessible($relatedClass, $relatedId, $model, $relationName);
    }

    private static function assertMorphRelation(
        Model $model,
        string $relationName,
        mixed $morphType,
        mixed $relatedId,
        bool $required,
    ): void {
        $hasType = $morphType !== null && $morphType !== '';
        $hasId = $relatedId !== null && $relatedId !== '';

        if ($hasType xor $hasId) {
            throw new InvalidArgumentException(sprintf(
                '%s %s type and id must both be present or both be null.',
                $model::class,
                $relationName,
            ));
        }

        if (! $hasType) {
            if ($required) {
                self::throwMissingRelation($model, $relationName);
            }

            return;
        }

        $relatedClass = Relation::getMorphedModel((string) $morphType) ?? (string) $morphType;

        if (! is_string($relatedClass) || ! class_exists($relatedClass) || ! is_a($relatedClass, Model::class, true)) {
            throw new AuthorizationException(sprintf(
                'The %s relation for %s does not resolve to an Eloquent model.',
                $relationName,
                $model::class,
            ));
        }

        self::assertAccessible($relatedClass, $relatedId, $model, $relationName);
    }

    /**
     * @param  class-string<Model>  $relatedClass
     */
    private static function assertAccessible(string $relatedClass, mixed $relatedId, Model $model, string $relationName): void
    {
        if (! $relatedClass::query()->whereKey($relatedId)->exists()) {
            throw new AuthorizationException(sprintf(
                'The %s relation for %s is not accessible in the current owner scope.',
                $relationName,
                $model::class,
            ));
        }
    }

    private static function ownerScopeConfig(Model $model): OwnerScopeConfig
    {
        $config = method_exists($model, 'ownerScopeConfig') ? $model->ownerScopeConfig() : null;

        return $config instanceof OwnerScopeConfig ? $config : new OwnerScopeConfig(enabled: true);
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
