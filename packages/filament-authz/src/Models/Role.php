<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role as SpatieRole;

final class Role extends SpatieRole
{
    use HasUuids;

    protected static function booted(): void
    {
        static::addGlobalScope('owner-team', function (Builder $builder): void {
            if (! config('filament-authz.owner.enabled', false) || ! config('permission.teams')) {
                return;
            }

            $teamColumn = (string) config('permission.column_names.team_foreign_key', 'team_id');
            $qualifiedTeamColumn = $builder->getModel()->qualifyColumn($teamColumn);
            $owner = OwnerContext::resolve();
            $includeGlobal = (bool) config('filament-authz.owner.include_global', false);

            if ($owner === null) {
                $builder->whereNull($qualifiedTeamColumn);

                return;
            }

            $builder->where(function (Builder $query) use ($includeGlobal, $owner, $qualifiedTeamColumn): void {
                $query->where($qualifiedTeamColumn, $owner->getKey());

                if ($includeGlobal) {
                    $query->orWhereNull($qualifiedTeamColumn);
                }
            });
        });

        static::creating(function (self $role): void {
            if (! config('filament-authz.owner.enabled', false) || ! config('permission.teams')) {
                return;
            }

            $teamColumn = (string) config('permission.column_names.team_foreign_key', 'team_id');
            $owner = OwnerContext::resolve();
            $includeGlobal = (bool) config('filament-authz.owner.include_global', false);

            if ($owner === null) {
                if (! $includeGlobal) {
                    throw new AuthorizationException('Role writes require an owner context.');
                }

                $providedTeamId = $role->getAttribute($teamColumn);

                if ($providedTeamId !== null && $providedTeamId !== '') {
                    throw new AuthorizationException('Global roles cannot be assigned to a tenant.');
                }

                $role->setAttribute($teamColumn, null);

                return;
            }

            $providedTeamId = $role->getAttribute($teamColumn);

            if ($providedTeamId === null || $providedTeamId === '') {
                $role->setAttribute($teamColumn, $owner->getKey());

                return;
            }

            if ((string) $providedTeamId !== (string) $owner->getKey()) {
                throw new AuthorizationException('Role writes must stay within the current owner context.');
            }
        });
    }

    public function getTable(): string
    {
        $table = config('permission.table_names.roles');

        if (is_string($table) && $table !== '') {
            return $table;
        }

        return parent::getTable();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithoutOwnerScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('owner-team');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForOwner(Builder $query, ?Model $owner = null, bool $includeGlobal = false): Builder
    {
        if (! config('permission.teams')) {
            return $query;
        }

        $teamColumn = (string) config('permission.column_names.team_foreign_key', 'team_id');
        $qualifiedTeamColumn = $query->getModel()->qualifyColumn($teamColumn);
        $owner ??= OwnerContext::resolve();

        /** @var Builder<self> $scoped */
        $scoped = $this->scopeWithoutOwnerScope($query);

        if ($owner === null) {
            return $scoped->whereNull($qualifiedTeamColumn);
        }

        return $scoped->where(function (Builder $builder) use ($includeGlobal, $owner, $qualifiedTeamColumn): void {
            $builder->where($qualifiedTeamColumn, $owner->getKey());

            if ($includeGlobal) {
                $builder->orWhereNull($qualifiedTeamColumn);
            }
        });
    }
}
