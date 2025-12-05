<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Enums;

enum CommissionRuleType: string
{
    case Product = 'product';
    case Category = 'category';
    case Affiliate = 'affiliate';
    case Program = 'program';
    case Volume = 'volume';
    case Promotion = 'promotion';
    case FirstPurchase = 'first_purchase';
    case Recurring = 'recurring';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product-specific',
            self::Category => 'Category-based',
            self::Affiliate => 'Affiliate-specific',
            self::Program => 'Program-wide',
            self::Volume => 'Volume-based',
            self::Promotion => 'Promotional',
            self::FirstPurchase => 'First Purchase Bonus',
            self::Recurring => 'Recurring Commission',
        };
    }

    public function priority(): int
    {
        return match ($this) {
            self::Promotion => 100,
            self::Product => 90,
            self::Category => 80,
            self::Volume => 70,
            self::Affiliate => 60,
            self::FirstPurchase => 50,
            self::Recurring => 40,
            self::Program => 10,
        };
    }
}
