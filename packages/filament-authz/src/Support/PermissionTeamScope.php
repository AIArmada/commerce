<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Query\Builder;

final class PermissionTeamScope
{
    public static function isEnabled(): bool
    {
        return (bool) config('filament-authz.owner.enabled', false)
            && (bool) config('permission.teams');
    }

    public static function includeGlobal(): bool
    {
        return (bool) config('filament-authz.owner.include_global', false);
    }

    public static function apply(Builder $query, string $tableAlias = ''): Builder
    {
        if (! self::isEnabled()) {
            return $query;
        }

        $column = self::teamColumn($tableAlias);
        $owner = OwnerContext::resolve();
        $includeGlobal = self::includeGlobal();

        if ($owner === null) {
            return $query->whereNull($column);
        }

        return $query->where(function (Builder $builder) use ($column, $owner, $includeGlobal): void {
            $builder->where($column, $owner->getKey());

            if ($includeGlobal) {
                $builder->orWhereNull($column);
            }
        });
    }

    public static function teamColumn(string $tableAlias = ''): string
    {
        $column = (string) config('permission.column_names.team_foreign_key', 'team_id');

        if ($tableAlias === '') {
            return $column;
        }

        return "{$tableAlias}.{$column}";
    }
}
