<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support\Macros;

use Filament\Navigation\NavigationItem;

class NavigationItemMacros
{
    public static function register(): void
    {
        NavigationItem::macro('requiresPermission', function (string $permission): static {
            return $this->visible(fn (): bool => auth()->user()?->can($permission) ?? false);
        });

        NavigationItem::macro('requiresRole', function (string | array $roles): static {
            $rolesArray = is_array($roles) ? $roles : [$roles];

            return $this->visible(fn (): bool => auth()->user()?->hasAnyRole($rolesArray) ?? false);
        });
    }
}
