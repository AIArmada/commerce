<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Settings\AffiliateBonusSettings;
use AIArmada\Affiliates\Support\BonusConfig;
use Illuminate\Support\Facades\Artisan;

describe('BonusConfig', function (): void {
    beforeEach(function (): void {
        // Settings cache outlives RefreshDatabase rollbacks within a process.
        Artisan::call('settings:clear-cache');
    });

    it('uses the settings group name', function (): void {
        expect(AffiliateBonusSettings::group())->toBe('affiliate-bonuses');
    });

    it('excludes performance bonuses from program rule cases', function (): void {
        $cases = CommissionRuleType::programRuleCases();

        expect($cases)->not->toContain(
            CommissionRuleType::TopPerformer,
            CommissionRuleType::Recruitment,
            CommissionRuleType::Consistency,
            CommissionRuleType::Growth,
        )
            ->and($cases)->toContain(CommissionRuleType::Product, CommissionRuleType::Program)
            ->and($cases)->toHaveCount(count(CommissionRuleType::cases()) - 4);
    });

    it('ships migration defaults through the normal migrate flow', function (): void {
        $settings = app(AffiliateBonusSettings::class);

        expect($settings->topPerformerEnabled)->toBeTrue()
            ->and($settings->topPerformerPositions)->toBe([1 => 50000, 2 => 25000, 3 => 10000])
            ->and($settings->recruitmentMinRecruits)->toBe(3)
            ->and($settings->consistencyMinWeeks)->toBe(4)
            ->and($settings->growthMinGrowthPercent)->toBe(25.0)
            ->and($settings->growthMinPreviousRevenueCurrency)->toBe('MYR');
    });

    it('falls back to config when settings cannot load', function (): void {
        config(['affiliates.bonuses.top_performer' => [
            'enabled' => true,
            'min_revenue' => 1000,
            'min_revenue_currency' => 'USD',
            'positions' => [1 => 7500],
        ]]);

        // An unloadable settings object: property access throws before any
        // value is read, exercising the config fallback branch.
        $unloadable = (new ReflectionClass(AffiliateBonusSettings::class))->newInstanceWithoutConstructor();
        app()->instance(AffiliateBonusSettings::class, $unloadable);

        expect(BonusConfig::topPerformer())->toBe([
            'enabled' => true,
            'min_revenue' => 1000,
            'min_revenue_currency' => 'USD',
            'positions' => [1 => 7500],
        ]);
    });

    it('prefers settings values once saved', function (): void {
        config(['affiliates.bonuses.recruitment' => [
            'enabled' => true,
            'bonus_per_recruit' => 2500,
            'min_recruits' => 3,
            'max_bonus' => 25000,
        ]]);

        (new AffiliateBonusSettings([
            'topPerformerEnabled' => true,
            'topPerformerPositions' => [1 => 50000, 2 => 25000, 3 => 10000],
            'topPerformerMinRevenue' => 100000,
            'topPerformerMinRevenueCurrency' => 'MYR',
            'recruitmentEnabled' => true,
            'recruitmentBonusPerRecruit' => 5000,
            'recruitmentMinRecruits' => 2,
            'recruitmentMaxBonus' => 50000,
            'consistencyEnabled' => true,
            'consistencyBonusAmount' => 5000,
            'consistencyMinWeeks' => 4,
            'consistencyMinConversionsPerWeek' => 1,
            'growthEnabled' => true,
            'growthBonusAmount' => 10000,
            'growthMinGrowthPercent' => 25.0,
            'growthMinPreviousRevenue' => 50000,
            'growthMinPreviousRevenueCurrency' => 'MYR',
        ]))->save();

        expect(BonusConfig::recruitment())->toBe([
            'enabled' => true,
            'bonus_per_recruit' => 5000,
            'min_recruits' => 2,
            'max_bonus' => 50000,
        ]);
    });
});
