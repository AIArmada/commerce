<?php

declare(strict_types=1);

namespace AIArmada\Tax\Queries;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class TaxOwnerScope
{
    /**
     * Resolve the owner for a tax calculation or zone lookup.
     *
     * @param  array<string, mixed>  $context
     */
    public static function resolve(array $context): ?Model
    {
        if (! config('tax.features.owner.enabled', false)) {
            return null;
        }

        $owner = $context['owner'] ?? OwnerContext::resolve();

        if ($owner !== null && ! $owner instanceof Model) {
            throw new InvalidArgumentException('Tax owner context must be an Eloquent model or null.');
        }

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            'Tax calculation requires an owner context or explicit global context.',
        );

        return $owner;
    }

    /**
     * Apply the package owner boundary to a tax query.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $context
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, array $context): Builder
    {
        if (! config('tax.features.owner.enabled', false)) {
            return $query;
        }

        $owner = self::resolve($context);

        return OwnerQuery::applyToEloquentBuilder(
            $query->withoutGlobalScope(OwnerScope::class),
            $owner,
            includeGlobal: self::includesGlobal(),
        );
    }

    public static function includesGlobal(): bool
    {
        return ! OwnerScopeOverride::suppressIncludeGlobal()
            && (bool) config('tax.features.owner.include_global', false);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<int|string>  $ids
     * @return Collection<int, TModel>
     */
    public static function lockWritable(Builder $query, array $ids): Collection
    {
        $ids = array_values(array_unique($ids));

        if (config('tax.features.owner.enabled', false)) {
            $query = OwnerQuery::applyToEloquentBuilder(
                $query->withoutGlobalScope(OwnerScope::class),
                self::resolve([]),
                includeGlobal: false,
            );
        }

        $records = $query->whereKey($ids)
            ->orderBy($query->getModel()->getQualifiedKeyName())
            ->lockForUpdate()
            ->get();

        if ($records->count() !== count($ids)) {
            throw new AuthorizationException('Tax record is not accessible in the current owner scope.');
        }

        return $records;
    }
}
