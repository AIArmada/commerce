<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use Filament\Resources\Resource;

abstract class BaseProductResource extends Resource
{
    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-products.navigation.group', 'Catalog');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'description'];
    }
}
