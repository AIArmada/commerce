<?php

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource;
use AIArmada\FilamentPricing\Resources\PromotionResource;
use AIArmada\Pricing\Enums\PromotionType;
use AIArmada\Pricing\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->owner1 = User::create(['name' => 'Owner 1', 'email' => 'owner1@example.com', 'password' => 'password']);
    $this->owner2 = User::create(['name' => 'Owner 2', 'email' => 'owner2@example.com', 'password' => 'password']);
});

test('pricing and affiliates navigation badges are owner-scoped', function () {
    // 1. Setup data for Owner 1
    OwnerContext::withOwner($this->owner1, function () {
        // Promotion
        Promotion::create([
            'name' => 'Owner 1 Promo',
            'type' => PromotionType::Percentage,
            'discount_value' => 10,
            'is_active' => true,
        ]);

        // Affiliate + Fraud Signal
        $affiliate = Affiliate::create([
            'code' => 'AFF1',
            'name' => 'Affiliate 1',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'MYR',
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'velocity',
            'risk_points' => 80,
            'severity' => FraudSeverity::High,
            'description' => 'High velocity detected',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);
    });

    // 2. Setup data for Owner 2
    OwnerContext::withOwner($this->owner2, function () {
        // 2 Promotions
        Promotion::create([
            'name' => 'Owner 2 Promo 1',
            'type' => PromotionType::Percentage,
            'discount_value' => 20,
            'is_active' => true,
        ]);
        Promotion::create([
            'name' => 'Owner 2 Promo 2',
            'type' => PromotionType::Fixed,
            'discount_value' => 500,
            'is_active' => true,
        ]);

        // Affiliate + 2 Fraud Signals
        $affiliate = Affiliate::create([
            'code' => 'AFF2',
            'name' => 'Affiliate 2',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1500,
            'currency' => 'MYR',
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'pattern',
            'risk_points' => 90,
            'severity' => FraudSeverity::Critical,
            'description' => 'Suspicious pattern',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);
        AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'geo_anomaly',
            'risk_points' => 70,
            'severity' => FraudSeverity::Medium,
            'description' => 'Geo anomaly',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);
    });

    // 3. Assert for Owner 1
    OwnerContext::withOwner($this->owner1, function () {
        expect(PromotionResource::getNavigationBadge())->toBe('1');
        expect(AffiliateFraudSignalResource::getNavigationBadge())->toBe('1');
    });

    // 4. Assert for Owner 2
    OwnerContext::withOwner($this->owner2, function () {
        expect(PromotionResource::getNavigationBadge())->toBe('2');
        expect(AffiliateFraudSignalResource::getNavigationBadge())->toBe('2');
    });
});
