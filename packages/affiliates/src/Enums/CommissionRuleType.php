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
    case TopPerformer = 'top_performer';
    case Recruitment = 'recruitment';
    case Consistency = 'consistency';
    case Growth = 'growth';

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
            self::TopPerformer => 'Top Performer Bonus',
            self::Recruitment => 'Recruitment Bonus',
            self::Consistency => 'Consistency Bonus',
            self::Growth => 'Growth Bonus',
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
            self::TopPerformer => 30,
            self::Recruitment => 20,
            self::Consistency => 15,
            self::Growth => 5,
            self::Program => 10,
        };
    }

    public function isPerformanceBonus(): bool
    {
        return match ($this) {
            self::TopPerformer,
            self::Recruitment,
            self::Consistency,
            self::Growth => true,
            default => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function performanceBonusCases(): array
    {
        return [
            self::TopPerformer,
            self::Recruitment,
            self::Consistency,
            self::Growth,
        ];
    }
}
