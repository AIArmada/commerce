<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Support;

use Filament\Actions\Action;
use Illuminate\Support\Facades\Gate;

final class FilamentTaxAuthz
{
    public static function check(string $ability, mixed ...$arguments): bool
    {
        return Gate::allows($ability, $arguments);
    }

    public static function requirePermission(Action $action, string $permission): Action
    {
        return $action->authorize(fn (): bool => static::check($permission));
    }
}
