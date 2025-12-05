<?php

declare(strict_types=1);

use AIArmada\Vouchers\Campaigns\Enums\CampaignObjective;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Enums\CampaignType;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Campaigns\Models\CampaignVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->campaign = Campaign::create([
        'name' => 'A/B Test Campaign',
        'type' => CampaignType::Promotional,
        'objective' => CampaignObjective::RevenueIncrease,
        'status' => CampaignStatus::Active,
        'ab_testing_enabled' => true,
        'spent_cents' => 0,
        'current_redemptions' => 0,
        'timezone' => 'UTC',
    ]);

    $this->controlVariant = CampaignVariant::create([
        'campaign_id' => $this->campaign->id,
        'name' => 'Control - 10% Off',
        'variant_code' => 'A',
        'traffic_percentage' => 50,
        'is_control' => true,
        'impressions' => 1000,
        'applications' => 200,
        'conversions' => 50,
        'revenue_cents' => 500000,
        'discount_cents' => 50000,
    ]);

    $this->treatmentVariant = CampaignVariant::create([
        'campaign_id' => $this->campaign->id,
        'name' => 'Treatment - 15% Off',
        'variant_code' => 'B',
        'traffic_percentage' => 50,
        'is_control' => false,
        'impressions' => 1000,
        'applications' => 200,
        'conversions' => 75,
        'revenue_cents' => 600000,
        'discount_cents' => 90000,
    ]);
});

describe('CampaignVariant Model', function (): void {
    it('can be created with required attributes', function (): void {
        expect($this->controlVariant)->toBeInstanceOf(CampaignVariant::class)
            ->and($this->controlVariant->name)->toBe('Control - 10% Off')
            ->and($this->controlVariant->variant_code)->toBe('A')
            ->and($this->controlVariant->is_control)->toBeTrue();
    });

    it('belongs to a campaign', function (): void {
        expect($this->controlVariant->campaign)->toBeInstanceOf(Campaign::class)
            ->and($this->controlVariant->campaign->id)->toBe($this->campaign->id);
    });
});

describe('CampaignVariant Metrics', function (): void {
    it('calculates conversion rate', function (): void {
        // 50 conversions / 200 applications = 25%
        expect($this->controlVariant->conversion_rate)->toBe(25.0);

        // 75 conversions / 200 applications = 37.5%
        expect($this->treatmentVariant->conversion_rate)->toBe(37.5);
    });

    it('calculates application rate', function (): void {
        // 200 applications / 1000 impressions = 20%
        expect($this->controlVariant->application_rate)->toBe(20.0);
    });

    it('calculates average order value', function (): void {
        // 500000 cents / 50 conversions = 10000 cents = $100
        expect($this->controlVariant->average_order_value)->toBe(10000.0);
    });

    it('calculates net revenue', function (): void {
        // 500000 - 50000 = 450000
        expect($this->controlVariant->net_revenue)->toBe(450000);
    });

    it('returns zero conversion rate when no applications', function (): void {
        $variant = CampaignVariant::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Empty Variant',
            'variant_code' => 'C',
            'traffic_percentage' => 0,
            'is_control' => false,
            'impressions' => 100,
            'applications' => 0,
            'conversions' => 0,
            'revenue_cents' => 0,
            'discount_cents' => 0,
        ]);

        expect($variant->conversion_rate)->toBe(0.0);
    });

    it('returns null average order value when no conversions', function (): void {
        $variant = CampaignVariant::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'No Conversions',
            'variant_code' => 'D',
            'traffic_percentage' => 0,
            'is_control' => false,
            'impressions' => 100,
            'applications' => 50,
            'conversions' => 0,
            'revenue_cents' => 0,
            'discount_cents' => 0,
        ]);

        expect($variant->average_order_value)->toBeNull();
    });
});

describe('CampaignVariant Recording', function (): void {
    it('can record an impression', function (): void {
        $initialImpressions = $this->controlVariant->impressions;

        $this->controlVariant->recordImpression();

        expect($this->controlVariant->fresh()->impressions)->toBe($initialImpressions + 1);
    });

    it('can record an application', function (): void {
        $initialApplications = $this->controlVariant->applications;

        $this->controlVariant->recordApplication();

        expect($this->controlVariant->fresh()->applications)->toBe($initialApplications + 1);
    });

    it('can record a conversion with revenue and discount', function (): void {
        $initialConversions = $this->controlVariant->conversions;
        $initialRevenue = $this->controlVariant->revenue_cents;
        $initialDiscount = $this->controlVariant->discount_cents;

        $this->controlVariant->recordConversion(10000, 1000);

        $fresh = $this->controlVariant->fresh();

        expect($fresh->conversions)->toBe($initialConversions + 1)
            ->and($fresh->revenue_cents)->toBe($initialRevenue + 10000)
            ->and($fresh->discount_cents)->toBe($initialDiscount + 1000);
    });
});

describe('CampaignVariant Statistical Significance', function (): void {
    it('calculates statistical significance between variants', function (): void {
        $stats = $this->treatmentVariant->calculateSignificance($this->controlVariant);

        expect($stats)->not->toBeNull()
            ->and($stats)->toHaveKeys(['z_score', 'p_value', 'significant'])
            ->and($stats['z_score'])->toBeNumeric()
            ->and($stats['p_value'])->toBeNumeric()
            ->and($stats['significant'])->toBeBool();
    });

    it('returns null when sample size is too small', function (): void {
        $smallControl = CampaignVariant::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Small Control',
            'variant_code' => 'E',
            'traffic_percentage' => 50,
            'is_control' => true,
            'impressions' => 50,
            'applications' => 20,
            'conversions' => 5,
            'revenue_cents' => 50000,
            'discount_cents' => 5000,
        ]);

        $smallTreatment = CampaignVariant::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Small Treatment',
            'variant_code' => 'F',
            'traffic_percentage' => 50,
            'is_control' => false,
            'impressions' => 50,
            'applications' => 20,
            'conversions' => 8,
            'revenue_cents' => 80000,
            'discount_cents' => 8000,
        ]);

        $stats = $smallTreatment->calculateSignificance($smallControl);

        expect($stats)->toBeNull();
    });

    it('detects significant difference with large sample and clear winner', function (): void {
        $highConvertingVariant = CampaignVariant::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'High Converting',
            'variant_code' => 'G',
            'traffic_percentage' => 50,
            'is_control' => false,
            'impressions' => 5000,
            'applications' => 1000,
            'conversions' => 400, // 40% conversion rate
            'revenue_cents' => 4000000,
            'discount_cents' => 400000,
        ]);

        $lowConvertingControl = CampaignVariant::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Low Converting Control',
            'variant_code' => 'H',
            'traffic_percentage' => 50,
            'is_control' => true,
            'impressions' => 5000,
            'applications' => 1000,
            'conversions' => 200, // 20% conversion rate
            'revenue_cents' => 2000000,
            'discount_cents' => 200000,
        ]);

        $stats = $highConvertingVariant->calculateSignificance($lowConvertingControl);

        expect($stats)->not->toBeNull()
            ->and($stats['significant'])->toBeTrue()
            ->and($stats['z_score'])->toBeGreaterThan(0);
    });
});

describe('CampaignVariant Comparison', function (): void {
    it('compares performance to another variant', function (): void {
        $comparison = $this->treatmentVariant->compareToVariant($this->controlVariant);

        expect($comparison)->toHaveKeys(['conversion_lift', 'revenue_lift', 'aov_lift'])
            ->and($comparison['conversion_lift'])->toBeGreaterThan(0) // Treatment has higher conversion
            ->and($comparison['revenue_lift'])->toBeGreaterThan(0); // Treatment has higher revenue
    });

    it('calculates conversion lift correctly', function (): void {
        // Control: 25% conversion rate
        // Treatment: 37.5% conversion rate
        // Lift: (37.5 - 25) / 25 * 100 = 50%

        $comparison = $this->treatmentVariant->compareToVariant($this->controlVariant);

        expect($comparison['conversion_lift'])->toBe(50.0);
    });

    it('calculates revenue lift correctly', function (): void {
        // Control: 500000 cents
        // Treatment: 600000 cents
        // Lift: (600000 - 500000) / 500000 * 100 = 20%

        $comparison = $this->treatmentVariant->compareToVariant($this->controlVariant);

        expect($comparison['revenue_lift'])->toBe(20.0);
    });
});

describe('CampaignVariant Scopes', function (): void {
    it('can scope to control variants', function (): void {
        $controls = CampaignVariant::control()->get();

        expect($controls)->toHaveCount(1)
            ->and($controls->first()->variant_code)->toBe('A');
    });

    it('can scope to treatment variants', function (): void {
        $treatments = CampaignVariant::treatment()->get();

        expect($treatments)->toHaveCount(1)
            ->and($treatments->first()->variant_code)->toBe('B');
    });

    it('can order by conversion rate', function (): void {
        $ordered = CampaignVariant::orderByConversionRate('desc')->get();

        expect($ordered->first()->variant_code)->toBe('B') // Higher conversion rate
            ->and($ordered->last()->variant_code)->toBe('A');
    });
});
