<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Support\Macros;

use Filament\Actions\Action;

class ActionMacros
{
    public static function register(): void
    {
        Action::macro('requiresPermission', function (string $permission): static {
            /** @var Action $this */
            return $this
                ->authorize(fn (): bool => auth()->user()?->can($permission) ?? false)
                ->visible(fn (): bool => auth()->user()?->can($permission) ?? false);
        });

        Action::macro('requiresRole', function (string|array $roles): static {
            /** @var Action $this */
            $rolesArray = is_array($roles) ? $roles : [$roles];

            return $this
                ->authorize(fn (): bool => auth()->user()?->hasAnyRole($rolesArray) ?? false)
                ->visible(fn (): bool => auth()->user()?->hasAnyRole($rolesArray) ?? false);
        });
    }
}
