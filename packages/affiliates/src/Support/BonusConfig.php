<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support;

use AIArmada\Affiliates\Settings\AffiliateBonusSettings;
use Throwable;

/**
 * Performance bonus configuration, settings-first with config fallback.
 *
 * Values come from the `affiliate-bonuses` settings group (managed through
 * the Filament adapter) and fall back to `affiliates.bonuses.*` config when
 * settings are unmigrated or unavailable.
 */
final class BonusConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function topPerformer(): array
    {
        try {
            $settings = app(AffiliateBonusSettings::class);

            return [
                'enabled' => $settings->topPerformerEnabled,
                'positions' => $settings->topPerformerPositions,
                'min_revenue' => $settings->topPerformerMinRevenue,
                'min_revenue_currency' => $settings->topPerformerMinRevenueCurrency,
            ];
        } catch (Throwable) {
            /** @var array<string, mixed> $config */
            $config = config('affiliates.bonuses.top_performer', []);

            return $config;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function recruitment(): array
    {
        try {
            $settings = app(AffiliateBonusSettings::class);

            return [
                'enabled' => $settings->recruitmentEnabled,
                'bonus_per_recruit' => $settings->recruitmentBonusPerRecruit,
                'min_recruits' => $settings->recruitmentMinRecruits,
                'max_bonus' => $settings->recruitmentMaxBonus,
            ];
        } catch (Throwable) {
            /** @var array<string, mixed> $config */
            $config = config('affiliates.bonuses.recruitment', []);

            return $config;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function consistency(): array
    {
        try {
            $settings = app(AffiliateBonusSettings::class);

            return [
                'enabled' => $settings->consistencyEnabled,
                'bonus_amount' => $settings->consistencyBonusAmount,
                'min_weeks' => $settings->consistencyMinWeeks,
                'min_conversions_per_week' => $settings->consistencyMinConversionsPerWeek,
            ];
        } catch (Throwable) {
            /** @var array<string, mixed> $config */
            $config = config('affiliates.bonuses.consistency', []);

            return $config;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function growth(): array
    {
        try {
            $settings = app(AffiliateBonusSettings::class);

            return [
                'enabled' => $settings->growthEnabled,
                'bonus_amount' => $settings->growthBonusAmount,
                'min_growth_percent' => $settings->growthMinGrowthPercent,
                'min_previous_revenue' => $settings->growthMinPreviousRevenue,
                'min_previous_revenue_currency' => $settings->growthMinPreviousRevenueCurrency,
            ];
        } catch (Throwable) {
            /** @var array<string, mixed> $config */
            $config = config('affiliates.bonuses.growth', []);

            return $config;
        }
    }
}
