<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Support\Macros;

use AIArmada\FilamentPermissions\Services\PermissionAggregator;
use Filament\Navigation\NavigationItem;

class NavigationMacros
{
    public static function register(): void
    {
        NavigationItem::macro('visibleForPermission', function (string $permission): static {
            /** @var NavigationItem $this */
            return $this->visible(function () use ($permission): bool {
                $user = auth()->user();
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

            return $this->visible(fn (): bool => auth()->user()?->hasAnyRole($rolesArray) ?? false);
        });

        NavigationItem::macro('visibleForAnyPermission', function (array $permissions): static {
            /** @var NavigationItem $this */
            return $this->visible(function () use ($permissions): bool {
                $user = auth()->user();
                if ($user === null) {
                    return false;
                }

                $aggregator = app(PermissionAggregator::class);

                return $aggregator->userHasAnyPermission($user, $permissions);
            });
        });

        NavigationItem::macro('visibleForAllPermissions', function (array $permissions): static {
            /** @var NavigationItem $this */
            return $this->visible(function () use ($permissions): bool {
                $user = auth()->user();
                if ($user === null) {
                    return false;
                }

                $aggregator = app(PermissionAggregator::class);

                return $aggregator->userHasAllPermissions($user, $permissions);
            });
        });
    }
}
