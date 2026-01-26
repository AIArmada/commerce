<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

test('AffiliateConversion has affiliate relationship', function (): void {
    $conversion = new AffiliateConversion;

    expect($conversion->affiliate())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateConversion has attribution relationship', function (): void {
    $conversion = new AffiliateConversion;

    expect($conversion->attribution())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateConversion has payout relationship', function (): void {
    $conversion = new AffiliateConversion;

    expect($conversion->payout())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateConversion can be created with fillable attributes', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF1',
        'name' => 'Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => 'AFF1',
        'order_reference' => 'ORD123',
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
    ]);

    expect($conversion->affiliate_id)->toBe($affiliate->id);
    expect($conversion->affiliate_code)->toBe('AFF1');
    expect($conversion->order_reference)->toBe('ORD123');
    expect($conversion->commission_minor)->toBe(500);
    expect($conversion->commission_currency)->toBe('USD');
    expect($conversion->status)->toBeInstanceOf(ApprovedConversion::class);
});
