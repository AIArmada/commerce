<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkReader;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateLink;
use AIArmada\Affiliates\Features\Programs\Api\TierQualificationReader;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\RejectedConversion;

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
            'min_revenue_currency' => 'USD',
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
            'min_revenue_currency' => 'USD',
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
        expect(app(TierQualificationReader::class)->qualifies($affiliate->id, $this->program->id, $tier->id))->toBeFalse();
    });

    test('meetsUpgradeRequirements returns false when revenue below minimum', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Gold',
            'level' => 3,
            'commission_rate_basis_points' => 1000,
            'min_conversions' => 0,
            'min_revenue' => 100000,
            'min_revenue_currency' => 'USD',
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
        expect(app(TierQualificationReader::class)->qualifies($affiliate->id, $this->program->id, $tier->id))->toBeFalse();
    });

    test('meetsUpgradeRequirements prefers neutral revenue value', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Neutral Revenue Tier',
            'level' => 4,
            'commission_rate_basis_points' => 1200,
            'min_conversions' => 1,
            'min_revenue' => 100000,
            'min_revenue_currency' => 'USD',
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

        expect(app(TierQualificationReader::class)->qualifies($affiliate->id, $this->program->id, $tier->id))->toBeTrue();
    });

    test('meetsUpgradeRequirements returns true when all requirements met', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Entry Level',
            'level' => 1,
            'commission_rate_basis_points' => 500,
            'min_conversions' => 0,
            'min_revenue' => 0,
            'min_revenue_currency' => 'USD',
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
        expect(app(TierQualificationReader::class)->qualifies($affiliate->id, $this->program->id, $tier->id))->toBeTrue();
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
            'min_revenue_currency' => 'USD',
            'benefits' => $benefits,
        ]);

        expect($tier->benefits)->toBeArray();
        expect($tier->benefits['priority_support'])->toBeTrue();
        expect($tier->benefits['custom_links'])->toBe(5);
        expect($tier->benefits['exclusive_offers'])->toContain('summer_promo');
    });

    test('program link volume counts each conversion once and excludes another affiliate link', function (): void {
        $attributes = [
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ];
        $affiliate = Affiliate::create($attributes + ['code' => 'LINK-VOLUME-A', 'name' => 'Link Volume A']);
        $other = Affiliate::create($attributes + ['code' => 'LINK-VOLUME-B', 'name' => 'Link Volume B']);
        $linkAttributes = [
            'program_id' => $this->program->id,
            'destination_url' => 'https://example.com/offer',
            'tracking_url' => 'https://example.com/go/offer',
        ];
        $link = AffiliateLink::create($linkAttributes + ['affiliate_id' => $affiliate->id]);
        $otherLink = AffiliateLink::create($linkAttributes + ['affiliate_id' => $other->id]);
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'affiliate_link_id' => $link->id,
        ]);

        $base = [
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'value_minor' => 10000,
            'commission_minor' => 500,
            'commission_currency' => 'USD',
            'occurred_at' => now(),
        ];
        foreach ([
            ['external_reference' => 'volume-direct-and-attribution', 'affiliate_link_id' => $link->id, 'affiliate_attribution_id' => $attribution->id, 'status' => ApprovedConversion::class],
            ['external_reference' => 'volume-attribution-only', 'affiliate_attribution_id' => $attribution->id, 'status' => ApprovedConversion::class],
            ['external_reference' => 'volume-other-link', 'affiliate_link_id' => $otherLink->id, 'status' => ApprovedConversion::class],
            ['external_reference' => 'volume-rejected', 'affiliate_link_id' => $link->id, 'status' => RejectedConversion::class],
        ] as $conversion) {
            AffiliateConversion::create($base + $conversion);
        }

        $reader = app(TrackingLinkReader::class);
        $approved = $reader->programVolume($affiliate->id, $this->program->id, 'USD', approvedOnly: true);
        $all = $reader->programVolume($affiliate->id, $this->program->id, 'USD');

        expect($approved->count)->toBe(2)
            ->and($approved->byCurrency)->toBe(['USD' => 20000])
            ->and($all->count)->toBe(3)
            ->and($all->byCurrency)->toBe(['USD' => 30000])
            ->and($reader->belongsTo($otherLink->id, $affiliate->id))->toBeFalse();
    });
});
