<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;

describe('AffiliateProgramTier Model', function (): void {
    beforeEach(function (): void {
        $this->program = AffiliateProgram::create([
            'name' => 'Test Program ' . uniqid(),
            'slug' => 'test-program-' . uniqid(),
            'status' => ProgramStatus::Active,
            'is_public' => true,
            'requires_approval' => false,
            'default_commission_rate_basis_points' => 500,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);
    });

    test('has many memberships', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Gold',
            'level' => 3,
            'commission_rate_basis_points' => 1000,
            'min_conversions' => 50,
            'min_revenue' => 500000,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'TIER' . uniqid(),
            'name' => 'Tier Test Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateProgramMembership::create([
            'program_id' => $this->program->id,
            'affiliate_id' => $affiliate->id,
            'tier_id' => $tier->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now(),
        ]);

        expect($tier->memberships)->toHaveCount(1);
        expect($tier->memberships->first()->affiliate_id)->toBe($affiliate->id);
    });

    test('meetsUpgradeRequirements returns false when conversions below minimum', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Silver',
            'level' => 2,
            'commission_rate_basis_points' => 750,
            'min_conversions' => 10,
            'min_revenue' => 0,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'UPGRADE' . uniqid(),
            'name' => 'Upgrade Test Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        // Affiliate has no conversions
        expect($tier->meetsUpgradeRequirements($affiliate, $this->program))->toBeFalse();
    });

    test('meetsUpgradeRequirements returns false when revenue below minimum', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Gold',
            'level' => 3,
            'commission_rate_basis_points' => 1000,
            'min_conversions' => 0,
            'min_revenue' => 100000,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'REVTEST' . uniqid(),
            'name' => 'Revenue Test Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        $link = AffiliateLink::create([
            'affiliate_id' => $affiliate->id,
            'program_id' => $this->program->id,
            'destination_url' => 'https://example.com/revenue',
            'tracking_url' => 'https://example.com/revenue?aff=' . $affiliate->code,
        ]);

        // Create attribution for program
        // Create conversion with low total
        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'external_reference' => 'ORD-REV-001',
            'value_minor' => 50000, // Only 500 in minor units, below 100000 minimum
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'affiliate_link_id' => $link->id,
            'status' => ApprovedConversion::class,
            'occurred_at' => now(),
        ]);

        // Revenue is below minimum
        expect($tier->meetsUpgradeRequirements($affiliate, $this->program))->toBeFalse();
    });

    test('meetsUpgradeRequirements prefers neutral revenue value', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Neutral Revenue Tier',
            'level' => 4,
            'commission_rate_basis_points' => 1200,
            'min_conversions' => 1,
            'min_revenue' => 100000,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'REVQUAL' . uniqid(),
            'name' => 'Neutral Revenue Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        $link = AffiliateLink::create([
            'affiliate_id' => $affiliate->id,
            'program_id' => $this->program->id,
            'destination_url' => 'https://example.com/qualified',
            'tracking_url' => 'https://example.com/qualified?aff=' . $affiliate->code,
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'external_reference' => 'ORD-REV-002',
            'value_minor' => 125000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'affiliate_link_id' => $link->id,
            'status' => ApprovedConversion::class,
            'occurred_at' => now(),
        ]);

        expect($tier->meetsUpgradeRequirements($affiliate, $this->program))->toBeTrue();
    });

    test('meetsUpgradeRequirements returns true when all requirements met', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Entry Level',
            'level' => 1,
            'commission_rate_basis_points' => 500,
            'min_conversions' => 0,
            'min_revenue' => 0,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'QUALIFY' . uniqid(),
            'name' => 'Qualified Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        // Tier with 0 min requirements should pass
        expect($tier->meetsUpgradeRequirements($affiliate, $this->program))->toBeTrue();
    });

    test('can store benefits array', function (): void {
        $benefits = [
            'priority_support' => true,
            'custom_links' => 5,
            'exclusive_offers' => ['summer_promo', 'black_friday'],
        ];

        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'VIP',
            'level' => 10,
            'commission_rate_basis_points' => 2000,
            'min_conversions' => 500,
            'min_revenue' => 5000000,
            'benefits' => $benefits,
        ]);

        expect($tier->benefits)->toBeArray();
        expect($tier->benefits['priority_support'])->toBeTrue();
        expect($tier->benefits['custom_links'])->toBe(5);
        expect($tier->benefits['exclusive_offers'])->toContain('summer_promo');
    });
});
