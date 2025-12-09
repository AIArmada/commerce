<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\FilamentAffiliates\Services\AffiliateStatsAggregator;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Affiliate::query()->delete();
    AffiliateConversion::query()->delete();
});

function createAffiliate(array $overrides = []): Affiliate
{
    return Affiliate::create(array_merge([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Partner',
        'description' => null,
        'status' => AffiliateStatus::Draft,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ], $overrides));
}

test('stats widget aggregator summarizes affiliate program health', function (): void {
    $active = createAffiliate(['status' => AffiliateStatus::Active]);
    createAffiliate(['status' => AffiliateStatus::Pending, 'code' => 'PENDING1']);
    createAffiliate(['status' => AffiliateStatus::Draft, 'code' => 'DRAFT1']);

    AffiliateConversion::create([
        'affiliate_id' => $active->getKey(),
        'affiliate_code' => $active->code,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Pending,
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $active->getKey(),
        'affiliate_code' => $active->code,
        'commission_minor' => 200,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Paid,
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $active->getKey(),
        'affiliate_code' => $active->code,
        'commission_minor' => 300,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Approved,
    ]);

    $stats = app(AffiliateStatsAggregator::class)->overview();

    expect($stats)
        ->toMatchArray([
            'total_affiliates' => 3,
            'active_affiliates' => 1,
            'pending_affiliates' => 1,
            'total_conversions' => 3,
            'pending_commission_minor' => 500,
            'paid_commission_minor' => 200,
            'total_commission_minor' => 1000,
        ])
        ->and($stats['conversion_rate'])->toBeGreaterThan(66)
        ->and($stats['conversion_rate'])->toBeLessThan(67);
});
