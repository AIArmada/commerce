<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\RankQualificationReason;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Models\AffiliateRankHistory;
use AIArmada\Affiliates\States\Active;

test('AffiliateRankHistory isPromotion returns true when toRank exists and fromRank is null', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PROMO001',
        'name' => 'Promo Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $rank = AffiliateRank::create([
        'name' => 'Entry',
        'slug' => 'entry',
        'level' => 0,
        'commission_rate_basis_points' => 0,
    ]);

    $history = AffiliateRankHistory::create([
        'affiliate_id' => $affiliate->id,
        'from_rank_id' => null,
        'to_rank_id' => $rank->id,
        'reason' => RankQualificationReason::Initial,
        'qualified_at' => now(),
    ]);

    expect($history->isPromotion())->toBeTrue()
        ->and($history->isDemotion())->toBeFalse();
});

test('AffiliateRankHistory isDemotion returns true when fromRank exists and toRank is null', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'DEMO001',
        'name' => 'Demo Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $rank = AffiliateRank::create([
        'name' => 'Gold',
        'slug' => 'gold',
        'level' => 3,
        'commission_rate_basis_points' => 200,
    ]);

    $history = AffiliateRankHistory::create([
        'affiliate_id' => $affiliate->id,
        'from_rank_id' => $rank->id,
        'to_rank_id' => null,
        'reason' => RankQualificationReason::Demoted,
        'qualified_at' => now(),
    ]);

    expect($history->isDemotion())->toBeTrue()
        ->and($history->isPromotion())->toBeFalse();
});
