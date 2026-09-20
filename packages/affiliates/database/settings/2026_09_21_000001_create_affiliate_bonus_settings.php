<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('affiliate-bonuses.topPerformerEnabled', true);
        $this->migrator->add('affiliate-bonuses.topPerformerPositions', [1 => 50000, 2 => 25000, 3 => 10000]);
        $this->migrator->add('affiliate-bonuses.topPerformerMinRevenue', 100000);
        $this->migrator->add('affiliate-bonuses.topPerformerMinRevenueCurrency', 'MYR');
        $this->migrator->add('affiliate-bonuses.recruitmentEnabled', true);
        $this->migrator->add('affiliate-bonuses.recruitmentBonusPerRecruit', 2500);
        $this->migrator->add('affiliate-bonuses.recruitmentMinRecruits', 3);
        $this->migrator->add('affiliate-bonuses.recruitmentMaxBonus', 25000);
        $this->migrator->add('affiliate-bonuses.consistencyEnabled', true);
        $this->migrator->add('affiliate-bonuses.consistencyBonusAmount', 5000);
        $this->migrator->add('affiliate-bonuses.consistencyMinWeeks', 4);
        $this->migrator->add('affiliate-bonuses.consistencyMinConversionsPerWeek', 1);
        $this->migrator->add('affiliate-bonuses.growthEnabled', true);
        $this->migrator->add('affiliate-bonuses.growthBonusAmount', 10000);
        $this->migrator->add('affiliate-bonuses.growthMinGrowthPercent', 25.0);
        $this->migrator->add('affiliate-bonuses.growthMinPreviousRevenue', 50000);
        $this->migrator->add('affiliate-bonuses.growthMinPreviousRevenueCurrency', 'MYR');
    }
};
