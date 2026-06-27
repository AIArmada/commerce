<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class OwnerScopedQuery
{
    /**
     * @var array<string, bool>
     */
    private static array $columnExistsCache = [];

    public static function apply(Builder $query, ?Model $owner = null, ?bool $includeGlobal = null): Builder
    {
        $owner ??= self::resolveOwner();

        $model = $query->getModel();
        $modelSupportsOwnerScope = method_exists($model, 'scopeForOwner');

        $requiresOwnerContext = self::requiresOwnerContext();

        if ($owner === null) {
            return ($requiresOwnerContext || $modelSupportsOwnerScope) ? self::empty($query) : $query;
        }

        $includeGlobal ??= false;

        if ($modelSupportsOwnerScope) {
            $model->scopeForOwner($query, $owner, $includeGlobal);

            return $query;
        }

        if (! $requiresOwnerContext) {
            return $query;
        }

        if (self::modelHasColumn($model, 'user_id')) {
            return self::applyViaBillableIdSubquery($query, 'user_id', $owner, $includeGlobal);
        }

        if (self::modelHasColumn($model, 'billable_type') && self::modelHasColumn($model, 'billable_id')) {
            return self::applyViaBillableMorphSubquery($query, $owner, $includeGlobal);
        }

        return self::empty($query);
    }

    private static function resolveOwner(): ?Model
    {
        return OwnerContext::resolve();
    }

    private static function requiresOwnerContext(): bool
    {
        $billableModel = (string) config('cashier.models.billable', 'App\\Models\\User');

        if (! class_exists($billableModel)) {
            return false;
        }

        $billable = new $billableModel;

        return method_exists($billable, 'scopeForOwner');
    }

    private static function modelHasColumn(Model $model, string $column): bool
    {
        $table = $model->getTable();
        $connection = $model->getConnectionName() ?? config('database.default');
        $cacheKey = "{$connection}:{$table}:{$column}";

        return self::$columnExistsCache[$cacheKey] ??= Schema::connection($connection)->hasColumn($table, $column);
    }

    private static function applyViaBillableIdSubquery(Builder $query, string $foreignKey, Model $owner, bool $includeGlobal): Builder
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

        $billablesQuery = $billableModel::query();
        $billable->scopeForOwner($billablesQuery, $owner, $includeGlobal);
        $billables = $billablesQuery->select($billableKeyName);

        return $query->whereIn($foreignKey, $billables);
    }

    private static function applyViaBillableMorphSubquery(Builder $query, Model $owner, bool $includeGlobal): Builder
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

        $billablesQuery = $billableModel::query();
        $billable->scopeForOwner($billablesQuery, $owner, $includeGlobal);
        $billables = $billablesQuery->select($billableKeyName);

        return $query
            ->where('billable_type', $billable->getMorphClass())
            ->whereIn('billable_id', $billables);
    }

    private static function empty(Builder $query): Builder
    {
        $model = $query->getModel();

        if (method_exists($model, 'scopeWithoutOwnerScope')) {
            $model->scopeWithoutOwnerScope($query);

            return $query->whereRaw('1 = 0');
        }

        return $query->whereRaw('1 = 0');
    }
}
