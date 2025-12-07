<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Support\Macros;

use AIArmada\FilamentPermissions\Services\PermissionAggregator;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Auth;

class NavigationMacros
{
    public static function register(): void
    {
        NavigationItem::macro('visibleForPermission', function (string $permission): static {
            /** @var NavigationItem $this */
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

        NavigationItem::macro('visibleForRole', function (string|array $roles): static {
            /** @var NavigationItem $this */
            $rolesArray = is_array($roles) ? $roles : [$roles];

            /** @phpstan-ignore return.type, method.notFound */
            return $this->visible(fn (): bool => Auth::user()?->hasAnyRole($rolesArray) ?? false);
        });

        NavigationItem::macro('visibleForAnyPermission', function (array $permissions): static {
            /** @var NavigationItem $this */
            /** @phpstan-ignore return.type */
            return $this->visible(function () use ($permissions): bool {
                $user = Auth::user();
                if ($user === null) {
                    return false;
                }

                $aggregator = app(PermissionAggregator::class);

                return $aggregator->userHasAnyPermission($user, $permissions);
            });
        });

        NavigationItem::macro('visibleForAllPermissions', function (array $permissions): static {
            /** @var NavigationItem $this */
            /** @phpstan-ignore return.type */
            return $this->visible(function () use ($permissions): bool {
                $user = Auth::user();
                if ($user === null) {
                    return false;
                }

                $aggregator = app(PermissionAggregator::class);

                return $aggregator->userHasAllPermissions($user, $permissions);
            });
        });
    }
}
