<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Support\Macros;

use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;

class TableComponentMacros
{
    public static function register(): void
    {
        Column::macro('requiresPermission', function (string $permission): static {
            /** @var Column $this */
            return $this->visible(fn (): bool => auth()->user()?->can($permission) ?? false);
        });

        Column::macro('requiresRole', function (string|array $roles): static {
            /** @var Column $this */
            $rolesArray = is_array($roles) ? $roles : [$roles];

            return $this->visible(fn (): bool => auth()->user()?->hasAnyRole($rolesArray) ?? false);
        });

        BaseFilter::macro('requiresPermission', function (string $permission): static {
            /** @var BaseFilter $this */
            return $this->visible(fn (): bool => auth()->user()?->can($permission) ?? false);
        });

        BaseFilter::macro('requiresRole', function (string|array $roles): static {
            /** @var BaseFilter $this */
            $rolesArray = is_array($roles) ? $roles : [$roles];

            return $this->visible(fn (): bool => auth()->user()?->hasAnyRole($rolesArray) ?? false);
        });
    }
}
