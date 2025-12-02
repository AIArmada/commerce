<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources;

use Filament\Resources\Resource;
use UnitEnum;

abstract class BaseJntResource extends Resource
{
    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    abstract protected static function navigationSortKey(): string;

    final public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-jnt.navigation_group');
    }

    final public static function getNavigationSort(): ?int
    {
        return config('filament-jnt.resources.navigation_sort.'.static::navigationSortKey());
    }

    final public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::count();

        return $count > 0 ? (string) $count : null;
    }

    final public static function getNavigationBadgeColor(): ?string
    {
        return config('filament-jnt.navigation_badge_color', 'primary');
    }

    protected static function pollingInterval(): string
    {
        return (string) config('filament-jnt.polling_interval', '30s');
    }
}
