<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class CashierOwnerScope
{
    /**
     * Apply owner scoping to a query.
     *
     * Strategy:
     * - If no owner context is available, return query unchanged.
     * - If the model supports `scopeForOwner`, use it.
     * - Else, if the model has a `user_id` column and the billable model supports owner scoping,
     *   scope via a subquery of billable IDs for the current owner.
     * - Else, fail closed (empty query) when an owner context exists.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, ?Model $owner = null, ?bool $includeGlobal = null): Builder
    {
        $owner ??= self::resolveOwner();

        if ($owner === null) {
            return $query;
        }

        $includeGlobal ??= false;
        $model = $query->getModel();

        if (method_exists($model, 'scopeForOwner')) {
            /** @phpstan-ignore-next-line dynamic local scope */
            return $query->forOwner($owner, $includeGlobal);
        }

        if (self::modelHasUserId($model)) {
            return self::applyViaUserId($query, $owner, $includeGlobal);
        }

        return self::empty($query);
    }

    private static function resolveOwner(): ?Model
    {
        return OwnerContext::resolve();
    }

    private static function modelHasUserId(Model $model): bool
    {
        return Schema::hasColumn($model->getTable(), 'user_id');
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function applyViaUserId(Builder $query, Model $owner, bool $includeGlobal): Builder
    {
        $billableModel = (string) config('cashier.models.billable', 'App\\Models\\User');

        if (! class_exists($billableModel)) {
            return self::empty($query);
        }

        $billable = new $billableModel;

        if (! method_exists($billable, 'scopeForOwner')) {
            return self::empty($query);
        }

        $billableKeyName = $billable->getKeyName();

        /** @var Builder<Model> $billables */
        $billables = $billableModel::query();

        /** @phpstan-ignore-next-line dynamic local scope */
        $billables = $billables->forOwner($owner, $includeGlobal)->select($billableKeyName);

        return $query->whereIn('user_id', $billables);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function empty(Builder $query): Builder
    {
        return $query->whereRaw('1 = 0');
    }
}
