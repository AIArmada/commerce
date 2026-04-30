<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Support;

use Filament\Actions\Action;

final class FilamentTaxAuthz
{
    /**
     * Apply authorization to a Filament action.
     *
     * - Prefer action macro integration if available.
     * - Always enforce server-side permission checks via Laravel's authorization layer.
     */
    public static function requirePermission(Action $action, string $permission): Action
    {
        $action->authorize(fn (): bool => self::check($permission));

        if (! Action::hasMacro('requiresPermission')) {
            return $action;
        }

        /** @phpstan-ignore-next-line method.notFound */
        return $action->requiresPermission($permission);
    }

    /**
     * Check whether the current user has the given permission.
     *
     * Uses Laravel's authorization layer (`can`) so optional integrations
     * (e.g. filament-authz + spatie/permission) can participate naturally.
     */
    public static function check(string $permission): bool
    {
        return auth()->check() && (auth()->user()?->can($permission) ?? false);
    }
}
