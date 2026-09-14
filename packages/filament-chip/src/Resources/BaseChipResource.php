<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

abstract class BaseChipResource extends Resource
{
    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    private const int NAVIGATION_BADGE_CACHE_TTL_SECONDS = 60;

    abstract protected static function navigationSortKey(): string;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $model = $query->getModel();

        if (method_exists($model, 'scopeForOwner')) {
            return $model->scopeForOwner($query);
        }

        return $query->whereRaw('1 = 0');
    }

    final public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-chip.navigation.group');
    }

    final public static function getNavigationSort(): ?int
    {
        return config('filament-chip.resources.navigation_sort.' . static::navigationSortKey());
    }

    final public static function getNavigationBadge(): ?string
    {
        $count = (int) OwnerCache::remember(
            OwnerContext::resolve(),
            'filament-chip.nav-badge.' . static::navigationSortKey(),
            self::NAVIGATION_BADGE_CACHE_TTL_SECONDS,
            static fn (): int => (int) static::getEloquentQuery()->count(),
        );

        return $count > 0 ? (string) $count : null;
    }

    final public static function getNavigationBadgeColor(): ?string
    {
        return config('filament-chip.navigation.badge_color', 'primary');
    }

    protected static function pollingInterval(): string
    {
        return (string) config('filament-chip.polling_interval', '45s');
    }
}
