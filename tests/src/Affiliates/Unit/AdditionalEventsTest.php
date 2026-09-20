<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\RankQualificationReason;
use AIArmada\Affiliates\Events\AffiliateActivated;
use AIArmada\Affiliates\Events\AffiliateRankChanged;
use AIArmada\Affiliates\Events\AffiliateTierUpgraded;
use AIArmada\Affiliates\Events\DailyStatsAggregated;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\States\Active;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

test('AffiliateActivated event uses Dispatchable trait', function (): void {
    expect(in_array(Dispatchable::class, class_uses_recursive(AffiliateActivated::class)))->toBeTrue();
});

test('AffiliateActivated event uses SerializesModels trait', function (): void {
    expect(in_array(SerializesModels::class, class_uses_recursive(AffiliateActivated::class)))->toBeTrue();
});

test('AffiliateTierUpgraded event allows null fromTier for initial tier assignment', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'TIER002',
        'name' => 'Initial Tier Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Initial Tier Program',
        'slug' => 'initial-tier-program',
        'status' => 'active',
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $toTier = AffiliateProgramTier::create([
        'program_id' => $program->id,
        'name' => 'Bronze',
        'level' => 1,
        'commission_rate_basis_points' => 500,
    ]);

    $event = new AffiliateTierUpgraded($affiliate, $program, null, $toTier);

    expect($event->fromTier)->toBeNull();
    expect($event->toTier)->toBe($toTier);
});

test('DailyStatsAggregated event uses Dispatchable and SerializesModels traits', function (): void {
    $traits = class_uses_recursive(DailyStatsAggregated::class);

    expect(in_array(Dispatchable::class, $traits))->toBeTrue();
    expect(in_array(SerializesModels::class, $traits))->toBeTrue();
});

test('AffiliateRankChanged event allows null fromRank for initial rank', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'RANK002',
        'name' => 'Initial Rank Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $toRank = AffiliateRank::create([
        'name' => 'Entry',
        'slug' => 'entry',
        'level' => 0,
        'commission_rate_basis_points' => 0,
    ]);

    $event = new AffiliateRankChanged(
        affiliate: $affiliate,
        fromRank: null,
        toRank: $toRank,
        reason: RankQualificationReason::Initial
    );

    expect($event->fromRank)->toBeNull();
    expect($event->toRank)->toBe($toRank);
    expect($event->reason)->toBe(RankQualificationReason::Initial);
});

test('AffiliateRankChanged event detects demotion correctly', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'RANK003',
        'name' => 'Method Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $fromRank = AffiliateRank::create([
        'name' => 'Silver',
        'slug' => 'silver-2',
        'level' => 2,
        'commission_rate_basis_points' => 100,
    ]);

    $toRank = AffiliateRank::create([
        'name' => 'Gold',
        'slug' => 'gold',
        'level' => 3,
        'commission_rate_basis_points' => 200,
    ]);

    $event = new AffiliateRankChanged(
        affiliate: $affiliate,
        fromRank: $fromRank,
        toRank: $toRank,
        reason: RankQualificationReason::Qualified
    );

    // Test isPromotion - lower level number means higher rank
    expect($event->isPromotion())->toBeFalse();

    // Test isDemotion - toRank has higher level number (lower rank)
    expect($event->isDemotion())->toBeTrue();
});

test('AffiliateRankChanged event detects promotion correctly', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'RANK004',
        'name' => 'Promotion Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $fromRank = AffiliateRank::create([
        'name' => 'Gold',
        'slug' => 'gold-2',
        'level' => 3,
        'commission_rate_basis_points' => 200,
    ]);

    $toRank = AffiliateRank::create([
        'name' => 'Silver',
        'slug' => 'silver-3',
        'level' => 2,
        'commission_rate_basis_points' => 100,
    ]);

    $event = new AffiliateRankChanged(
        affiliate: $affiliate,
        fromRank: $fromRank,
        toRank: $toRank,
        reason: RankQualificationReason::Qualified
    );

    expect($event->isPromotion())->toBeTrue();
    expect($event->isDemotion())->toBeFalse();
});
