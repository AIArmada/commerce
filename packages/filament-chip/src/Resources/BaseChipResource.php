<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use Filament\Resources\Resource;
use UnitEnum;

abstract class BaseChipResource extends Resource
{
    abstract protected static function navigationSortKey(): string;

    final public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-chip.navigation_group');
    }

    final public static function getNavigationSort(): ?int
    {
        return config('filament-chip.resources.navigation_sort.'.static::navigationSortKey());
    }

    final public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::count();

        return $count > 0 ? (string) $count : null;
    }

    final public static function getNavigationBadgeColor(): ?string
    {
        return config('filament-chip.navigation_badge_color', 'primary');
    }

    protected static function pollingInterval(): string
    {
        return (string) config('filament-chip.polling_interval', '45s');
    }
}
