<?php

declare(strict_types=1);

namespace AIArmada\Products\Enums;

enum ProductVisibility: string
{
    case Catalog = 'catalog';
    case Search = 'search';
    case CatalogSearch = 'catalog_search';
    case Individual = 'individual';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Catalog => __('products::enums.visibility.catalog'),
            self::Search => __('products::enums.visibility.search'),
            self::CatalogSearch => __('products::enums.visibility.catalog_search'),
            self::Individual => __('products::enums.visibility.individual'),
            self::Hidden => __('products::enums.visibility.hidden'),
        };
    }

    public function inCatalog(): bool
    {
        return in_array($this, [self::Catalog, self::CatalogSearch]);
    }

    public function inSearch(): bool
    {
        return in_array($this, [self::Search, self::CatalogSearch]);
    }

    public function isDirectlyAccessible(): bool
    {
        return $this !== self::Hidden;
    }

    public function color(): string
    {
        return match ($this) {
            self::Catalog => 'info',
            self::Search => 'primary',
            self::CatalogSearch => 'success',
            self::Individual => 'warning',
            self::Hidden => 'gray',
        };
    }
}
