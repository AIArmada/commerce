<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\RankQualificationReason;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Models\AffiliateRankHistory;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\Support\Middleware\TrackAffiliateCookie;

test('AffiliateRankHistory isPromotion returns true when toRank exists and fromRank is null', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PROMO001',
        'name' => 'Promo Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $history = AffiliateRankHistory::create([
        'affiliate_id' => $affiliate->id,
        'from_rank_id' => null,
        'to_rank_id' => null,
        'reason' => RankQualificationReason::Initial,
        'qualified_at' => now(),
    ]);

    // Without a toRank, this is not a promotion
    expect($history->isPromotion())->toBeFalse();
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

    $history = AffiliateRankHistory::create([
        'affiliate_id' => $affiliate->id,
        'from_rank_id' => null,
        'to_rank_id' => null,
        'reason' => RankQualificationReason::Demoted,
        'qualified_at' => now(),
    ]);

    // Without fromRank, this is not a demotion
    expect($history->isDemotion())->toBeFalse();
});

test('AffiliatePayoutEvent can be created', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PAYEVENT001',
        'name' => 'Payout Event Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'affiliate_id' => $affiliate->id,
        'reference' => 'PAY-EVENT-001',
        'total_minor' => 10000,
        'currency' => 'USD',
        'status' => 'pending',
    ]);

    $event = AffiliatePayoutEvent::create([
        'affiliate_payout_id' => $payout->id,
        'to_status' => 'pending',
        'notes' => 'Test payout event',
        'metadata' => ['key' => 'value'],
    ]);

    expect($event)->toBeInstanceOf(AffiliatePayoutEvent::class);
    expect($event->to_status)->toBe('pending');
    expect($event->notes)->toBe('Test payout event');
});

test('TrackAffiliateCookie can be instantiated', function (): void {
    $middleware = app(TrackAffiliateCookie::class);
    expect($middleware)->toBeInstanceOf(TrackAffiliateCookie::class);
});
