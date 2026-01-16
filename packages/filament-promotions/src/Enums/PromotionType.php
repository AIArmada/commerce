<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Enums;

use AIArmada\Promotions\Enums\PromotionType as BasePromotionType;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Filament-aware extension of PromotionType with UI contracts.
 */
enum PromotionType: string implements HasColor, HasIcon, HasLabel
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case BuyXGetY = 'buy_x_get_y';

    public function getLabel(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage Off',
            self::Fixed => 'Fixed Amount',
            self::BuyXGetY => 'Buy X Get Y',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Percentage => Heroicon::OutlinedReceiptPercent->value,
            self::Fixed => Heroicon::OutlinedCurrencyDollar->value,
            self::BuyXGetY => Heroicon::OutlinedGift->value,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Percentage => 'success',
            self::Fixed => 'info',
            self::BuyXGetY => 'warning',
        };
    }

    /**
     * Convert from base PromotionType to Filament-aware version.
     */
    public static function fromBase(BasePromotionType $type): self
    {
        return self::from($type->value);
    }

    /**
     * Convert to base PromotionType.
     */
    public function toBase(): BasePromotionType
    {
        return BasePromotionType::from($this->value);
    }
}
