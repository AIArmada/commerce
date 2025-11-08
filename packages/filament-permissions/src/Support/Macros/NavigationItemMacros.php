<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Support\Macros;

use Filament\Navigation\NavigationItem;

class NavigationItemMacros
{
    public static function register(): void
    {
        NavigationItem::macro('requiresPermission', function (string $permission): static {
            /** @var NavigationItem $this */
            return $this->visible(fn (): bool => auth()->user()?->can($permission) ?? false);
        });

        NavigationItem::macro('requiresRole', function (string|array $roles): static {
            /** @var NavigationItem $this */
            $rolesArray = is_array($roles) ? $roles : [$roles];

            return $this->visible(fn (): bool => auth()->user()?->hasAnyRole($rolesArray) ?? false);
        });
    }
}
