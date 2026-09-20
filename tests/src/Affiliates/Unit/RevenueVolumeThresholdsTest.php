<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Models\AffiliateVolumeTier;
use AIArmada\Affiliates\Services\Commissions\CommissionRuleEngine;
use AIArmada\Affiliates\Services\RankQualificationService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\Support\RevenueVolume;
use AIArmada\CommerceSupport\Settings\ExchangeRateSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    // Settings cache outlives RefreshDatabase rollbacks within a process.
    Artisan::call('settings:clear-cache');
});

function makeThresholdAffiliate(string $code, string $currency = 'USD'): Affiliate
{
    return Affiliate::create([
        'code' => $code . '-' . uniqid(),
        'name' => 'Threshold Test',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => $currency,
    ]);
}

function recordThresholdConversion(Affiliate $affiliate, int $valueMinor, string $currency, string $ref, CarbonImmutable $at, ?string $linkId = null): void
{
    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'subject_key' => 'order:' . $ref,
        'subject_instance' => 'share',
        'external_reference' => $ref,
        'conversion_type' => 'purchase',
        'subtotal_minor' => $valueMinor,
        'value_minor' => $valueMinor,
        'commission_minor' => 0,
        'commission_currency' => $currency,
        'affiliate_link_id' => $linkId,
        'status' => ApprovedConversion::class,
        'occurred_at' => $at,
    ]);
}

function withThresholdRates(): void
{
    config(['affiliates.currency.default' => 'USD']);
    (new ExchangeRateSettings(['base' => 'USD', 'rates' => ['MYR' => 4.7], 'history' => []]))->save();
}

describe('RevenueVolume', function (): void {
    test('converts mixed legs when rates exist', function (): void {
        withThresholdRates();

        expect(RevenueVolume::measurableIn(['USD' => 10000, 'MYR' => 47000], 'USD'))->toBe(20000);
    });

    test('falls back to the reference leg without rates', function (): void {
        config(['affiliates.currency.default' => 'USD']);
        (new ExchangeRateSettings(['base' => 'USD', 'rates' => [], 'history' => []]))->save();

        expect(RevenueVolume::measurableIn(['USD' => 10000, 'MYR' => 47000], 'USD'))->toBe(10000)
            ->and(RevenueVolume::measurableIn(['MYR' => 47000], 'USD'))->toBe(0)
            ->and(RevenueVolume::measurableIn([], 'USD'))->toBe(0);
    });
});

describe('multicurrency threshold comparisons', function (): void {
    test('volume tiers measure converted volume', function (): void {
        withThresholdRates();
        $affiliate = makeThresholdAffiliate('VOL');

        AffiliateVolumeTier::create([
            'program_id' => null,
            'name' => 'High Roller',
            'min_volume_minor' => 150000,
            'max_volume_minor' => null,
            'commission_rate_basis_points' => 2000,
            'currency' => 'USD',
        ]);

        $now = CarbonImmutable::now();
        recordThresholdConversion($affiliate, 60000, 'USD', 'VOL-USD', $now);
        recordThresholdConversion($affiliate, 470000, 'MYR', 'VOL-MYR', $now);

        $result = app(CommissionRuleEngine::class)->calculate($affiliate, 10000, []);

        // Converted volume is 160000, clearing the 150000 tier: 20% - 10% default of 10000.
        expect($result->volumeBonusMinor)->toBe(1000);
    });

    test('volume tiers ignore unmeasurable legs without rates', function (): void {
        config(['affiliates.currency.default' => 'USD']);
        config(['commerce-support.currency.exchange_rates' => ['base' => 'USD', 'rates' => []]]);
        $affiliate = makeThresholdAffiliate('VOL');

        AffiliateVolumeTier::create([
            'program_id' => null,
            'name' => 'High Roller',
            'min_volume_minor' => 150000,
            'max_volume_minor' => null,
            'commission_rate_basis_points' => 2000,
            'currency' => 'USD',
        ]);

        $now = CarbonImmutable::now();
        recordThresholdConversion($affiliate, 60000, 'USD', 'VOL-USD', $now);
        recordThresholdConversion($affiliate, 470000, 'MYR', 'VOL-MYR', $now);

        $result = app(CommissionRuleEngine::class)->calculate($affiliate, 10000, []);

        // Only the USD 60000 leg is measurable: tier not reached.
        expect($result->volumeBonusMinor)->toBe(0);
    });

    test('growth bonuses compare converted revenue', function (): void {
        withThresholdRates();
        config(['affiliates.bonuses.growth' => [
            'enabled' => true,
            'min_growth_percent' => 50,
            'min_previous_revenue' => 10000,
            'bonus_amount' => 7500,
        ]]);
        $affiliate = makeThresholdAffiliate('GRO');

        $from = CarbonImmutable::now()->startOfMonth();
        $to = CarbonImmutable::now()->endOfMonth();
        recordThresholdConversion($affiliate, 50000, 'USD', 'GRO-PREV', $from->subMonth()->addDays(10));
        recordThresholdConversion($affiliate, 60000, 'USD', 'GRO-CUR-USD', $from->addDays(5));
        recordThresholdConversion($affiliate, 235000, 'MYR', 'GRO-CUR-MYR', $from->addDays(6));

        $bonuses = app(CommissionRuleEngine::class)->calculatePerformanceBonuses(
            CommissionRuleType::Growth,
            $from,
            $to
        );

        // Current revenue converts to 110000 vs previous 50000: +120%.
        expect($bonuses)->toHaveCount(1)
            ->and($bonuses[0]['affiliate_id'])->toBe($affiliate->id)
            ->and($bonuses[0]['metrics']['growth_percentage'])->toEqual(120.0);
    });

    test('leaderboard ranks on converted revenue', function (): void {
        withThresholdRates();
        config(['affiliates.bonuses.top_performer' => [
            'enabled' => true,
            'min_revenue' => 0,
            'positions' => [1 => 1000, 2 => 500],
        ]]);

        $usdAffiliate = makeThresholdAffiliate('TOP-USD');
        $myrAffiliate = makeThresholdAffiliate('TOP-MYR', 'MYR');

        $from = CarbonImmutable::now()->startOfMonth();
        $to = CarbonImmutable::now()->endOfMonth();
        recordThresholdConversion($usdAffiliate, 50000, 'USD', 'TOP-USD-1', $from->addDays(5));
        recordThresholdConversion($myrAffiliate, 470000, 'MYR', 'TOP-MYR-1', $from->addDays(5));

        $bonuses = app(CommissionRuleEngine::class)->calculatePerformanceBonuses(
            CommissionRuleType::TopPerformer,
            $from,
            $to
        );
        $byAffiliate = collect($bonuses)->keyBy('affiliate_id');

        // MYR 470000 converts to USD 100000, outranking the USD 50000 affiliate.
        expect($byAffiliate[$myrAffiliate->id]['metrics']['position'])->toBe(1)
            ->and($byAffiliate[$usdAffiliate->id]['metrics']['position'])->toBe(2);
    });

    test('rank metrics measure converted sales', function (): void {
        withThresholdRates();
        $affiliate = makeThresholdAffiliate('RNK');

        recordThresholdConversion($affiliate, 7000, 'USD', 'RNK-USD', CarbonImmutable::now()->subDays(5));
        recordThresholdConversion($affiliate, 47000, 'MYR', 'RNK-MYR', CarbonImmutable::now()->subDays(5));

        $metrics = app(RankQualificationService::class)->calculateMetrics($affiliate);

        expect($metrics['personal_sales'])->toBe(17000)
            ->and($metrics['lifetime_value'])->toBe(17000);
    });

    test('program tier upgrades measure converted revenue', function (): void {
        withThresholdRates();

        $program = AffiliateProgram::create([
            'name' => 'Threshold Program ' . uniqid(),
            'slug' => 'threshold-program-' . uniqid(),
            'status' => ProgramStatus::Active,
            'is_public' => true,
            'requires_approval' => false,
            'default_commission_rate_basis_points' => 500,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        $tier = AffiliateProgramTier::create([
            'program_id' => $program->id,
            'name' => 'Gold',
            'level' => 3,
            'commission_rate_basis_points' => 2000,
            'min_conversions' => 2,
            'min_revenue' => 150000,
            'min_revenue_currency' => 'USD',
        ]);

        $affiliate = makeThresholdAffiliate('TUP');
        $link = AffiliateLink::create([
            'affiliate_id' => $affiliate->id,
            'program_id' => $program->id,
            'destination_url' => 'https://example.com/threshold',
            'tracking_url' => 'https://example.com/threshold?aff=' . $affiliate->code,
        ]);

        $now = CarbonImmutable::now();
        recordThresholdConversion($affiliate, 60000, 'USD', 'TUP-USD', $now, $link->id);
        recordThresholdConversion($affiliate, 470000, 'MYR', 'TUP-MYR', $now, $link->id);

        // Converted revenue is 160000 across 2 conversions: qualifies.
        expect($tier->meetsUpgradeRequirements($affiliate, $program))->toBeTrue();
    });
});
