<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Models\AffiliateVolumeTier;
use AIArmada\Affiliates\Services\Commissions\CommissionRuleEngine;
use AIArmada\Affiliates\Services\RankQualificationService;
use AIArmada\Affiliates\Settings\AffiliatePayoutSettings;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\Support\PayoutMinimums;
use AIArmada\CommerceSupport\Settings\ExchangeRateSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    // Settings cache outlives RefreshDatabase rollbacks within a process.
    Artisan::call('settings:clear-cache');
});

function denominationsAffiliate(string $code = 'DENOM001'): Affiliate
{
    return Affiliate::create([
        'code' => $code,
        'name' => 'Denomination Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
}

function denominationsConversion(Affiliate $affiliate, int $valueMinor, string $reference, array $overrides = []): AffiliateConversion
{
    return AffiliateConversion::create(array_merge([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => $reference,
        'value_minor' => $valueMinor,
        'commission_minor' => (int) ($valueMinor / 10),
        'commission_currency' => 'MYR',
        'status' => ApprovedConversion::class,
        'occurred_at' => CarbonImmutable::now()->startOfMonth()->addDay(),
    ], $overrides));
}

function denominationsRates(): void
{
    (new ExchangeRateSettings(['base' => 'USD', 'rates' => ['MYR' => 4.7], 'history' => []]))->save();
}

test('payout minimums resolve per currency with a default fallback', function (): void {
    (new AffiliatePayoutSettings(['minimumAmount' => 5000, 'minimumAmountsByCurrency' => ['USD' => 1000]]))->save();

    expect(PayoutMinimums::forCurrency('USD'))->toBe(1000)
        ->and(PayoutMinimums::forCurrency('usd'))->toBe(1000)
        ->and(PayoutMinimums::forCurrency('MYR'))->toBe(5000);
});

test('new balances inherit the minimum of their own currency', function (): void {
    (new AffiliatePayoutSettings(['minimumAmount' => 5000, 'minimumAmountsByCurrency' => ['USD' => 1000]]))->save();

    $affiliate = denominationsAffiliate();
    $conversion = denominationsConversion($affiliate, 40000, 'ORD-DENOM-001', ['commission_currency' => 'USD']);

    app(ApplyConversionAccounting::class)->handle($conversion);

    expect($affiliate->balanceFor('USD')?->minimum_payout_minor)->toBe(1000);
});

test('volume tiers measure the boundary in the tier currency', function (): void {
    denominationsRates();
    $affiliate = denominationsAffiliate('DENOM002');

    AffiliateVolumeTier::create([
        'name' => 'MYR Tier',
        'min_volume_minor' => 40000,
        'max_volume_minor' => null,
        'commission_rate_basis_points' => 1500,
        'period' => 'monthly',
        'currency' => 'MYR',
    ]);

    // 40,000 MYR clears a 40,000 MYR floor but converts to ~8,511 USD.
    denominationsConversion($affiliate, 40000, 'ORD-DENOM-002');

    $result = app(CommissionRuleEngine::class)->calculate($affiliate, 10000);

    expect($result->volumeBonusMinor)->toBe(500)
        ->and($result->finalCommissionMinor)->toBe(1500);
});

test('program tier upgrades measure revenue in the tier currency', function (): void {
    denominationsRates();
    $affiliate = denominationsAffiliate('DENOM003');

    $program = AffiliateProgram::create([
        'name' => 'Denomination Program',
        'slug' => 'denomination-program',
        'status' => ProgramStatus::Active,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $tier = AffiliateProgramTier::create([
        'program_id' => $program->id,
        'name' => 'Silver',
        'level' => 2,
        'commission_rate_basis_points' => 1000,
        'min_conversions' => 1,
        'min_revenue' => 40000,
        'min_revenue_currency' => 'MYR',
    ]);

    $link = AffiliateLink::create([
        'affiliate_id' => $affiliate->id,
        'program_id' => $program->id,
        'destination_url' => 'https://example.com/landing',
        'tracking_url' => 'https://example.com/landing?ref=DENOM003',
    ]);

    denominationsConversion($affiliate, 40000, 'ORD-DENOM-003', ['affiliate_link_id' => $link->id]);

    expect($tier->meetsUpgradeRequirements($affiliate, $program))->toBeTrue();
});

test('program eligibility honors a denominated revenue rule', function (): void {
    denominationsRates();
    $affiliate = denominationsAffiliate('DENOM004');

    $program = AffiliateProgram::create([
        'name' => 'Eligibility Program',
        'slug' => 'eligibility-program',
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
        'eligibility_rules' => ['min_revenue' => 40000, 'min_revenue_currency' => 'MYR'],
    ]);

    denominationsConversion($affiliate, 40000, 'ORD-DENOM-004');

    expect($program->canJoin($affiliate))->toBeTrue();
});

test('top performer threshold compares in its declared currency', function (): void {
    denominationsRates();
    config(['affiliates.bonuses.top_performer.enabled' => true]);
    config(['affiliates.bonuses.top_performer.positions' => [1 => 5000]]);
    config(['affiliates.bonuses.top_performer.min_revenue' => 9000]);
    config(['affiliates.bonuses.top_performer.min_revenue_currency' => 'USD']);

    $affiliate = denominationsAffiliate('DENOM005');
    denominationsConversion($affiliate, 40000, 'ORD-DENOM-005');

    $bonuses = app(CommissionRuleEngine::class)->calculatePerformanceBonuses(
        CommissionRuleType::TopPerformer,
        CarbonImmutable::now()->startOfMonth(),
        CarbonImmutable::now()->endOfMonth(),
    );

    // 40,000 MYR converts to ~8,511 USD, below the 9,000 USD floor.
    expect($bonuses)->toBe([]);
});

test('growth previous-revenue floor compares in its declared currency', function (): void {
    denominationsRates();
    config(['affiliates.bonuses.growth.enabled' => true]);
    config(['affiliates.bonuses.growth.bonus_amount' => 10000]);
    config(['affiliates.bonuses.growth.min_growth_percent' => 25]);
    config(['affiliates.bonuses.growth.min_previous_revenue' => 37000]);
    config(['affiliates.bonuses.growth.min_previous_revenue_currency' => 'MYR']);

    $affiliate = denominationsAffiliate('DENOM006');

    denominationsConversion($affiliate, 40000, 'ORD-DENOM-006A', [
        'occurred_at' => CarbonImmutable::now()->subMonth()->startOfMonth()->addDay(),
    ]);
    denominationsConversion($affiliate, 80000, 'ORD-DENOM-006B');

    $bonuses = app(CommissionRuleEngine::class)->calculatePerformanceBonuses(
        CommissionRuleType::Growth,
        CarbonImmutable::now()->startOfMonth(),
        CarbonImmutable::now()->endOfMonth(),
    );

    // 40,000 MYR clears the 37,000 MYR floor; in USD (~8,511) it would not.
    expect($bonuses)->toHaveCount(1)
        ->and($bonuses[0]['affiliate_id'])->toBe($affiliate->id);
});

test('rank qualification measures sales in the rank currency', function (): void {
    denominationsRates();
    $affiliate = denominationsAffiliate('DENOM007');

    $rank = AffiliateRank::create([
        'name' => 'Silver',
        'slug' => 'silver-denom',
        'level' => 2,
        'commission_rate_basis_points' => 100,
        'min_personal_sales' => 40000,
        'min_team_sales' => 0,
        'min_active_downlines' => 0,
        'currency' => 'MYR',
    ]);

    denominationsConversion($affiliate, 40000, 'ORD-DENOM-007');

    expect(app(RankQualificationService::class)->evaluate($affiliate)?->id)->toBe($rank->id);
});
