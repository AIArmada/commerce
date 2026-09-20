<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\MatureConversion;
use AIArmada\Affiliates\Actions\Conversions\ProcessConversionMaturity;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\QualifiedConversion;

// MatureConversion Action Tests
test('MatureConversion returns false for non-qualified conversion', function (): void {
    $action = app(MatureConversion::class);

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

    $result = $action->handle($conversion);

    expect($result)->toBeFalse();
});

test('MatureConversion returns false for conversion with future maturity date', function (): void {
    $action = app(MatureConversion::class);

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

    $result = $action->handle($conversion);

    expect($result)->toBeFalse();
});

// ProcessConversionMaturity Action Tests
test('ProcessConversionMaturity matures due conversions and skips the rest', function (): void {
    $action = app(ProcessConversionMaturity::class);

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

    expect($action->handle())->toBe(1)
        ->and($due->fresh()->status->equals(ApprovedConversion::class))->toBeTrue();
});
