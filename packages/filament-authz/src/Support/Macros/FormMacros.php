<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support\Macros;

use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Auth;

class FormMacros
{
    public static function register(): void
    {
        Field::macro('visibleForPermission', function (string $permission): static {
            /** @var Field $this */
            return $this->visible(function () use ($permission): bool {
                $user = Auth::user();
                if ($user === null) {
                    return false;
                }

                $aggregator = app(PermissionAggregator::class);

                return $aggregator->userHasPermission($user, $permission);
            });
        });

        Field::macro('visibleForRole', function (string | array $roles): static {
            /** @var Field $this */
            $rolesArray = is_array($roles) ? $roles : [$roles];

            return $this->visible(fn (): bool => Auth::user()?->hasAnyRole($rolesArray) ?? false);
        });

        Field::macro('disabledWithoutPermission', function (string $permission): static {
            /** @var Field $this */
            return $this->disabled(function () use ($permission): bool {
                $user = Auth::user();
                if ($user === null) {
                    return true;
                }

                $aggregator = app(PermissionAggregator::class);

                return ! $aggregator->userHasPermission($user, $permission);
            });
        });

        Section::macro('visibleForPermission', function (string $permission): static {
            /** @var Section $this */
            return $this->visible(function () use ($permission): bool {
                $user = Auth::user();
                if ($user === null) {
                    return false;
                }

                $aggregator = app(PermissionAggregator::class);

                return $aggregator->userHasPermission($user, $permission);
            });
        });

        Section::macro('visibleForRole', function (string | array $roles): static {
            /** @var Section $this */
            $rolesArray = is_array($roles) ? $roles : [$roles];

            return $this->visible(fn (): bool => Auth::user()?->hasAnyRole($rolesArray) ?? false);
        });

        Section::macro('collapsedWithoutPermission', function (string $permission): static {
            /** @var Section $this */
            return $this->collapsed(function () use ($permission): bool {
                $user = Auth::user();
                if ($user === null) {
                    return true;
                }

                $aggregator = app(PermissionAggregator::class);

                return ! $aggregator->userHasPermission($user, $permission);
            });
        });
    }
}
