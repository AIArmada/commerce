<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayoutHold;
use AIArmada\Affiliates\States\Active;
use Carbon\CarbonImmutable;

describe('AffiliatePayoutHold Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'HOLD' . uniqid(),
            'name' => 'Hold Test Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('casts released_at as datetime', function (): void {
        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $this->affiliate->id,
            'reason' => 'released_hold',
            'placed_by' => 'admin@example.com',
            'released_at' => '2024-12-15 10:00:00',
        ]);

        expect($hold->released_at)->toBeInstanceOf(CarbonImmutable::class);
        expect($hold->released_at->format('Y-m-d'))->toBe('2024-12-15');
    });

    test('notes can be null', function (): void {
        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $this->affiliate->id,
            'reason' => 'quick_hold',
            'placed_by' => 'admin@example.com',
        ]);

        expect($hold->notes)->toBeNull();
    });
});
