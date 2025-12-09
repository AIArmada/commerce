<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support\Macros;

use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FilterMacros
{
    public static function register(): void
    {
        Filter::macro('visibleForPermission', function (string $permission): static {
            /** @var Filter $this */
            /** @phpstan-ignore return.type */
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
            /** @var Filter $this */
            $rolesArray = is_array($roles) ? $roles : [$roles];

            /** @phpstan-ignore return.type, method.notFound */
            return $this->visible(fn (): bool => Auth::user()?->hasAnyRole($rolesArray) ?? false);
        });

        SelectFilter::macro('roleOptions', function (): static {
            /** @var SelectFilter $this */
            /** @phpstan-ignore return.type */
            return $this->options(Role::pluck('name', 'id')->toArray());
        });

        SelectFilter::macro('permissionOptions', function (?string $prefix = null): static {
            /** @var SelectFilter $this */
            $query = Permission::query();

            if ($prefix !== null) {
                $query->where('name', 'like', $prefix . '%');
            }

            /** @phpstan-ignore return.type */
            return $this->options($query->pluck('name', 'id')->toArray());
        });

        SelectFilter::macro('permissionGroupOptions', function (): static {
            /** @var SelectFilter $this */
            $groups = Permission::all()
                ->groupBy(function (Permission $permission): string {
                    $parts = explode('.', $permission->name);

                    return $parts[0] ?? 'other';
                })
                ->keys()
                ->mapWithKeys(fn (string $group): array => [$group => ucfirst($group)])
                ->toArray();

            /** @phpstan-ignore return.type */
            return $this->options($groups);
        });
    }
}
