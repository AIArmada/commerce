<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Settings;

use Spatie\LaravelSettings\Settings;

class AffiliateBonusSettings extends Settings
{
    public bool $topPerformerEnabled;

    /**
     * Position to minor-unit bonus, e.g. [1 => 50000, 2 => 25000].
     */
    public array $topPerformerPositions;

    public int $topPerformerMinRevenue;

    public string $topPerformerMinRevenueCurrency;

    public bool $recruitmentEnabled;

    public int $recruitmentBonusPerRecruit;

    public int $recruitmentMinRecruits;

    public int $recruitmentMaxBonus;

    public bool $consistencyEnabled;

    public int $consistencyBonusAmount;

    public int $consistencyMinWeeks;

    public int $consistencyMinConversionsPerWeek;

    public bool $growthEnabled;

    public int $growthBonusAmount;

    public float $growthMinGrowthPercent;

    public int $growthMinPreviousRevenue;

    public string $growthMinPreviousRevenueCurrency;

    public static function group(): string
    {
        return 'affiliate-bonuses';
    }
}
