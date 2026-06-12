<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentJnt\Support\NavigationBadgeHelper;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

abstract class BaseJntResource extends Resource
{
    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    abstract protected static function navigationSortKey(): string;

    final public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-jnt.navigation.group');
    }

    final public static function getNavigationSort(): ?int
    {
        return config('filament-jnt.resources.navigation_sort.' . static::navigationSortKey());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (Filament::auth()?->user() !== null) && parent::shouldRegisterNavigation();
    }

    final public static function getNavigationBadge(): ?string
    {
        return NavigationBadgeHelper::getNavigationBadge(static::class, static::getEloquentQuery());
    }

    final public static function getNavigationBadgeColor(): ?string
    {
        return config('filament-jnt.navigation.badge_color', 'primary');
    }

    protected static function pollingInterval(): string
    {
        return (string) config('filament-jnt.polling_interval', '30s');
    }

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $includeGlobal = (bool) config('jnt.owner.include_global', false);

        return OwnerUiScope::apply(
            parent::getEloquentQuery(),
            includeGlobal: $includeGlobal,
        );
    }
}
