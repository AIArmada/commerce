<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support\Macros;

use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;

class FilterMacros
{
    public static function register(): void
    {
        Filter::macro('visibleForPermission', function (string $permission): static {
            return $this->visible(function () use ($permission): bool {
                $user = Auth::user();
                if ($user === null) {
                    return false;
                }

                $aggregator = app(PermissionAggregator::class);

                return $aggregator->userHasPermission($user, $permission);
            });
        });

        Filter::macro('visibleForRole', function (string | array $roles): static {
            $rolesArray = is_array($roles) ? $roles : [$roles];

            return $this->visible(fn (): bool => Auth::user()?->hasAnyRole($rolesArray) ?? false);
        });

        SelectFilter::macro('roleOptions', function (): static {
            return $this->options(Role::pluck('name', 'id')->toArray());
        });

        SelectFilter::macro('permissionOptions', function (?string $prefix = null): static {
            $query = Permission::query();

            if ($prefix !== null) {
                $query->where('name', 'like', $prefix . '%');
            }

            return $this->options($query->pluck('name', 'id')->toArray());
        });

        SelectFilter::macro('permissionGroupOptions', function (): static {
            $groups = Permission::all()
                ->groupBy(function (Permission $permission): string {
                    $parts = explode('.', $permission->name);

                    return $parts[0] ?? 'other';
                })
                ->keys()
                ->mapWithKeys(fn (string $group): array => [$group => ucfirst($group)])
                ->toArray();

            return $this->options($groups);
        });
    }
}
