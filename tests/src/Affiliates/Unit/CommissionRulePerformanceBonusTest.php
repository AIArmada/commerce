<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\Commissions\CommissionRuleEngine;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config()->set('affiliates.owner.enabled', false);
    $this->engine = app(CommissionRuleEngine::class);
    $this->from = CarbonImmutable::now()->startOfMonth();
    $this->to = CarbonImmutable::now()->endOfMonth();
});

it('keeps top performer output identical after the commission-rule collapse', function (): void {
    config()->set('affiliates.bonuses.top_performer', [
        'enabled' => true,
        'min_revenue' => 1000,
        'positions' => [1 => 7500],
    ]);

    $affiliate = Affiliate::create([
        'code' => 'TYPE-TOP',
        'name' => 'Top Performer',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'TYPE-TOP-ORDER',
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'status' => ApprovedConversion::class,
        'occurred_at' => $this->from->addDay(),
    ]);

    $bonuses = $this->engine->calculatePerformanceBonuses(
        CommissionRuleType::TopPerformer,
        $this->from,
        $this->to,
    );

    expect($bonuses)->toHaveCount(1)
        ->and($bonuses[0]['bonus_type'])->toBe('top_performer')
        ->and($bonuses[0]['affiliate_id'])->toBe($affiliate->id)
        ->and($bonuses[0]['amount_minor'])->toBe(7500)
        ->and($bonuses[0]['metrics'])->toMatchArray([
            'position' => 1,
            'total_revenue' => 10000,
            'total_conversions' => 1,
            'period' => $this->from->format('Y-m'),
        ]);
});

it('keeps recruitment output identical after the commission-rule collapse', function (): void {
    config()->set('affiliates.bonuses.recruitment', [
        'enabled' => true,
        'min_recruits' => 1,
        'bonus_per_recruit' => 2500,
        'max_bonus' => 25000,
    ]);

    $recruiter = Affiliate::create([
        'code' => 'TYPE-RECRUITER',
        'name' => 'Recruiter',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    Affiliate::create([
        'code' => 'TYPE-RECRUIT',
        'name' => 'Recruit',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
        'parent_affiliate_id' => $recruiter->id,
    ]);

    $bonuses = $this->engine->calculatePerformanceBonuses(
        CommissionRuleType::Recruitment,
        $this->from,
        $this->to,
    );

    expect($bonuses)->toHaveCount(1)
        ->and($bonuses[0]['bonus_type'])->toBe('recruitment')
        ->and($bonuses[0]['affiliate_id'])->toBe($recruiter->id)
        ->and($bonuses[0]['amount_minor'])->toBe(2500)
        ->and($bonuses[0]['metrics'])->toMatchArray([
            'recruit_count' => 1,
            'period' => $this->from->format('Y-m'),
        ]);
});

it('keeps consistency output identical after the commission-rule collapse', function (): void {
    config()->set('affiliates.bonuses.consistency', [
        'enabled' => true,
        'min_weeks' => 1,
        'min_conversions_per_week' => 1,
        'bonus_amount' => 5000,
    ]);

    $affiliate = Affiliate::create([
        'code' => 'TYPE-CONSISTENCY',
        'name' => 'Consistent Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'TYPE-CONSISTENCY-ORDER',
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'status' => ApprovedConversion::class,
        'occurred_at' => $this->from->startOfWeek()->addDay(),
    ]);

    $bonuses = $this->engine->calculatePerformanceBonuses(
        CommissionRuleType::Consistency,
        $this->from,
        $this->to,
    );

    expect($bonuses)->toHaveCount(1)
        ->and($bonuses[0]['bonus_type'])->toBe('consistency')
        ->and($bonuses[0]['affiliate_id'])->toBe($affiliate->id)
        ->and($bonuses[0]['amount_minor'])->toBe(5000)
        ->and($bonuses[0]['metrics'])->toMatchArray([
            'weeks_with_sales' => 1,
            'period' => $this->from->format('Y-m'),
        ]);
});

it('keeps growth output identical after the commission-rule collapse', function (): void {
    config()->set('affiliates.bonuses.growth', [
        'enabled' => true,
        'min_growth_percent' => 50,
        'min_previous_revenue' => 1000,
        'bonus_amount' => 7500,
    ]);

    $affiliate = Affiliate::create([
        'code' => 'TYPE-GROWTH',
        'name' => 'Growing Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'TYPE-GROWTH-PREVIOUS',
        'value_minor' => 1000,
        'commission_minor' => 100,
        'status' => ApprovedConversion::class,
        'occurred_at' => $this->from->subMonth()->addDay(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'TYPE-GROWTH-CURRENT',
        'value_minor' => 2000,
        'commission_minor' => 200,
        'status' => ApprovedConversion::class,
        'occurred_at' => $this->from->addDay(),
    ]);

    $bonuses = $this->engine->calculatePerformanceBonuses(
        CommissionRuleType::Growth,
        $this->from,
        $this->to,
    );

    expect($bonuses)->toHaveCount(1)
        ->and($bonuses[0]['bonus_type'])->toBe('growth')
        ->and($bonuses[0]['affiliate_id'])->toBe($affiliate->id)
        ->and($bonuses[0]['amount_minor'])->toBe(7500)
        ->and($bonuses[0]['metrics'])->toMatchArray([
            'current_revenue' => 2000,
            'previous_revenue' => 1000,
            'growth_percentage' => 100.0,
            'period' => $this->from->format('Y-m'),
        ]);
});
