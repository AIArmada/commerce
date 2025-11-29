<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\AffiliatePayout;

test('AffiliatePayout has conversions relationship', function (): void {
    $payout = new AffiliatePayout();

    expect($payout->conversions())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('AffiliatePayout has events relationship', function (): void {
    $payout = new AffiliatePayout();

    expect($payout->events())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('AffiliatePayout can be created with fillable attributes', function (): void {
    $payout = AffiliatePayout::create([
        'reference' => 'PAY123',
        'status' => 'pending',
        'total_minor' => 1000,
        'conversion_count' => 5,
        'currency' => 'USD',
        'metadata' => ['key' => 'value'],
    ]);

    expect($payout->reference)->toBe('PAY123');
    expect($payout->status)->toBe('pending');
    expect($payout->total_minor)->toBe(1000);
    expect($payout->conversion_count)->toBe(5);
    expect($payout->currency)->toBe('USD');
    expect($payout->metadata)->toBe(['key' => 'value']);
});