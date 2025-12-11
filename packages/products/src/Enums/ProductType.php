<?php

declare(strict_types=1);

namespace AIArmada\Products\Enums;

enum ProductType: string
{
    case Simple = 'simple';
    case Configurable = 'configurable';
    case Bundle = 'bundle';
    case Digital = 'digital';
    case Subscription = 'subscription';

    public function label(): string
    {
        return match ($this) {
            self::Simple => __('products::enums.type.simple'),
            self::Configurable => __('products::enums.type.configurable'),
            self::Bundle => __('products::enums.type.bundle'),
            self::Digital => __('products::enums.type.digital'),
            self::Subscription => __('products::enums.type.subscription'),
        };
    }

    public function hasVariants(): bool
    {
        return match ($this) {
            self::Configurable => true,
            default => false,
        };
    }

    public function isPhysical(): bool
    {
        return match ($this) {
            self::Simple, self::Configurable, self::Bundle => true,
            default => false,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Simple => 'heroicon-o-cube',
            self::Configurable => 'heroicon-o-squares-2x2',
            self::Bundle => 'heroicon-o-rectangle-group',
            self::Digital => 'heroicon-o-cloud-arrow-down',
            self::Subscription => 'heroicon-o-arrow-path',
        };
    }
}
