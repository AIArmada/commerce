<?php

declare(strict_types=1);

namespace AIArmada\Products\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Disabled = 'disabled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('products::enums.status.draft'),
            self::Active => __('products::enums.status.active'),
            self::Disabled => __('products::enums.status.disabled'),
            self::Archived => __('products::enums.status.archived'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Disabled => 'warning',
            self::Archived => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil',
            self::Active => 'heroicon-o-check-circle',
            self::Disabled => 'heroicon-o-pause-circle',
            self::Archived => 'heroicon-o-archive-box',
        };
    }

    public function isVisible(): bool
    {
        return $this === self::Active;
    }

    public function isPurchasable(): bool
    {
        return $this === self::Active;
    }
}
