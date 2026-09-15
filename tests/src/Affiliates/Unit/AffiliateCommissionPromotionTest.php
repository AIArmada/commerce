<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\States\Active;
use Carbon\CarbonImmutable;

describe('AffiliateCommissionPromotion Model', function (): void {
    beforeEach(function (): void {
        $this->program = AffiliateProgram::create([
            'name' => 'Promotion Program ' . uniqid(),
            'slug' => 'promotion-program-' . uniqid(),
            'status' => ProgramStatus::Active,
            'is_public' => true,
            'requires_approval' => false,
            'default_commission_rate_basis_points' => 500,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        $this->affiliate = Affiliate::create([
            'code' => 'PROMO' . uniqid(),
            'name' => 'Promo Test Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('appliesToAffiliate returns false when promotion not active', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Inactive Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addWeek(),
        ]);

        expect($promotion->appliesToAffiliate($this->affiliate))->toBeFalse();
    });

    test('calculateBonus returns zero for unknown type', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Unknown Type Promo',
            'bonus_type' => 'unknown',
            'bonus_value' => 1000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $bonus = $promotion->calculateBonus(5000);
        expect($bonus)->toBe(0);
    });

    test('can store conditions array', function (): void {
        $conditions = [
            'min_order_value' => 10000,
            'product_categories' => ['electronics', 'clothing'],
            'new_customers_only' => true,
        ];

        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Conditional Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 1000,
            'conditions' => $conditions,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        expect($promotion->conditions)->toBeArray();
        expect($promotion->conditions['min_order_value'])->toBe(10000);
        expect($promotion->conditions['product_categories'])->toContain('electronics');
        expect($promotion->conditions['new_customers_only'])->toBeTrue();
    });

    test('casts dates correctly', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Date Cast Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => '2024-12-01 00:00:00',
            'ends_at' => '2024-12-31 23:59:59',
        ]);

        expect($promotion->starts_at)->toBeInstanceOf(CarbonImmutable::class);
        expect($promotion->ends_at)->toBeInstanceOf(CarbonImmutable::class);
        expect($promotion->starts_at->format('Y-m-d'))->toBe('2024-12-01');
        expect($promotion->ends_at->format('Y-m-d'))->toBe('2024-12-31');
    });
});
