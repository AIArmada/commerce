<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use Filament\Resources\Resource;

abstract class BaseCatalogResource extends Resource
{
    protected static ?string $recordTitleAttribute = 'name';

    abstract protected static function navigationSortKey(): string;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-products.navigation.group', 'Catalog');
    }

    final public static function getNavigationSort(): ?int
    {
        return (int) config('filament-products.navigation.resources.' . static::navigationSortKey(), 2);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'description'];
    }
}
