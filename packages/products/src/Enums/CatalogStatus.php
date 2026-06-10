<?php

declare(strict_types=1);

namespace AIArmada\Products\Enums;

enum CatalogStatus: string
{
    case Active = 'active';
    case Hidden = 'hidden';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('products::enums.catalog_status.active'),
            self::Hidden => __('products::enums.catalog_status.hidden'),
            self::Archived => __('products::enums.catalog_status.archived'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Hidden => 'warning',
            self::Archived => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::Hidden => 'heroicon-o-eye-slash',
            self::Archived => 'heroicon-o-archive-box',
        };
    }

    public function isVisible(): bool
    {
        return $this === self::Active;
    }
}
