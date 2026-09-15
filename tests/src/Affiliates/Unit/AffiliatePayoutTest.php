<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingPayout;
use Carbon\CarbonImmutable;

describe('AffiliatePayout Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'PAYOUT' . uniqid(),
            'name' => 'Payout Test Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('has many conversions', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-CONV-' . uniqid(),
            'status' => CompletedPayout::class,
            'total_minor' => 75000,
            'conversion_count' => 3,
            'currency' => 'USD',
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'affiliate_payout_id' => $payout->id,
            'order_reference' => 'ORD-PAY-001',
            'total_minor' => 25000,
            'commission_minor' => 2500,
            'commission_currency' => 'USD',
            'status' => PaidConversion::class,
            'occurred_at' => now(),
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'affiliate_payout_id' => $payout->id,
            'order_reference' => 'ORD-PAY-002',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => PaidConversion::class,
            'occurred_at' => now(),
        ]);

        expect($payout->conversions)->toHaveCount(2);
    });

    test('has many events', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-EVT-' . uniqid(),
            'status' => PendingPayout::class,
            'total_minor' => 30000,
            'conversion_count' => 3,
            'currency' => 'USD',
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
        ]);

        AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'to_status' => 'created',
            'metadata' => ['note' => 'Payout created'],
        ]);

        AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'from_status' => 'created',
            'to_status' => 'approved',
            'metadata' => ['note' => 'Payout approved'],
        ]);

        expect($payout->events)->toHaveCount(2);
    });

    test('casts scheduled_at as datetime', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-SCHED-' . uniqid(),
            'status' => 'pending',
            'total_minor' => 15000,
            'conversion_count' => 2,
            'currency' => 'USD',
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
            'scheduled_at' => '2024-12-25 10:00:00',
        ]);

        expect($payout->scheduled_at)->toBeInstanceOf(CarbonImmutable::class);
        expect($payout->scheduled_at->format('Y-m-d'))->toBe('2024-12-25');
    });

    test('casts paid_at as datetime', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-PAID-' . uniqid(),
            'status' => 'completed',
            'total_minor' => 55000,
            'conversion_count' => 5,
            'currency' => 'USD',
            'payee_type' => Affiliate::class,
            'payee_id' => $this->affiliate->id,
            'paid_at' => '2024-12-20 14:30:00',
        ]);

        expect($payout->paid_at)->toBeInstanceOf(CarbonImmutable::class);
        expect($payout->paid_at->format('Y-m-d'))->toBe('2024-12-20');
    });
});
