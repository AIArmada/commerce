<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support\Macros;

use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;

class ColumnMacros
{
    public static function register(): void
    {
        Column::macro('visibleForPermission', function (string $permission): static {
            return $this->visible(function () use ($permission): bool {
                $user = Auth::user();
                if ($user === null) {
                    return false;
                }

                $aggregator = app(PermissionAggregator::class);

                return $aggregator->userHasPermission($user, $permission);
            });
        });

        Column::macro('visibleForRole', function (string | array $roles): static {
            $rolesArray = is_array($roles) ? $roles : [$roles];

            return $this->visible(fn (): bool => Auth::user()?->hasAnyRole($rolesArray) ?? false);
        });

        Column::macro('visibleForAnyPermission', function (array $permissions): static {
            return $this->visible(function () use ($permissions): bool {
                $user = Auth::user();
                if ($user === null) {
                    return false;
                }

                $aggregator = app(PermissionAggregator::class);

                return $aggregator->userHasAnyPermission($user, $permissions);
            });
        });

        TextColumn::macro('formatPermission', function (): static {
            return $this
                ->badge()
                ->color(fn (string $state): string => match (true) {
                    str_contains($state, 'delete') => 'danger',
                    str_contains($state, 'create') => 'success',
                    str_contains($state, 'update') => 'warning',
                    str_contains($state, 'view') => 'info',
                    default => 'gray',
                });
        });

        TextColumn::macro('formatRole', function (): static {
            return $this
                ->badge()
                ->color('primary');
        });
    }
}
