<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Conversions\Api\ConversionMaturity;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\Affiliates\Features\Conversions\States\PendingConversion;
use AIArmada\Affiliates\Features\Conversions\States\QualifiedConversion;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;

// MatureConversion Action Tests
test('MatureConversion returns false for non-qualified conversion', function (): void {
    $action = app(ConversionMaturity::class);

    $affiliate = Affiliate::create([
        'code' => 'MATURE001',
        'name' => 'Mature Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORD-MATURE-001',
        'total_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
        'occurred_at' => now()->subDays(60),
    ]);

    $result = $action->mature($conversion->id);

    expect($result)->toBeFalse();
});

test('MatureConversion returns false for conversion with future maturity date', function (): void {
    $action = app(ConversionMaturity::class);

    $affiliate = Affiliate::create([
        'code' => 'MATURE002',
        'name' => 'Future Mature Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORD-MATURE-002',
        'total_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => QualifiedConversion::class,
        'occurred_at' => now(), // Just occurred, not mature yet
    ]);

    $result = $action->mature($conversion->id);

    expect($result)->toBeFalse();
});

// ProcessConversionMaturity Action Tests
test('ProcessConversionMaturity matures due conversions and skips the rest', function (): void {
    $action = app(ConversionMaturity::class);

    $affiliate = Affiliate::create([
        'code' => 'MATURE003',
        'name' => 'Maturity Batch Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $due = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORD-MATURE-003',
        'total_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => QualifiedConversion::class,
        'occurred_at' => now()->subDays(60),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORD-MATURE-004',
        'total_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => QualifiedConversion::class,
        'occurred_at' => now(),
    ]);

    expect($action->process(dryRun: true))->toBe(1)
        ->and($due->fresh()->status->equals(QualifiedConversion::class))->toBeTrue()
        ->and($action->process())->toBe(1)
        ->and($due->fresh()->status->equals(ApprovedConversion::class))->toBeTrue()
        ->and($action->process())->toBe(0);
});
