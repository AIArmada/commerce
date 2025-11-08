<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Support\Macros;

use Filament\Widgets\Widget;

class WidgetMacros
{
    public static function register(): void
    {
        // Widget doesn't support direct macro but we provide a trait helper instead.
        // Users can override canView() in their widgets using our helper methods.
    }
}
