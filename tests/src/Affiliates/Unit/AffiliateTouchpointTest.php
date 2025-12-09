<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

test('AffiliateTouchpoint has attribution relationship', function (): void {
    $touchpoint = new AffiliateTouchpoint;

    expect($touchpoint->attribution())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateTouchpoint has affiliate relationship', function (): void {
    $touchpoint = new AffiliateTouchpoint;

    expect($touchpoint->affiliate())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateTouchpoint can be created with fillable attributes', function (): void {
    $touchpoint = AffiliateTouchpoint::create([
        'affiliate_attribution_id' => 1,
        'affiliate_id' => 1,
        'affiliate_code' => 'AFF1',
        'source' => 'google',
        'medium' => 'cpc',
        'campaign' => 'summer',
        'term' => 'shoes',
        'content' => 'ad1',
        'metadata' => ['key' => 'value'],
    ]);

    expect($touchpoint->affiliate_attribution_id)->toBe(1);
    expect($touchpoint->affiliate_id)->toBe(1);
    expect($touchpoint->affiliate_code)->toBe('AFF1');
    expect($touchpoint->source)->toBe('google');
    expect($touchpoint->medium)->toBe('cpc');
    expect($touchpoint->campaign)->toBe('summer');
    expect($touchpoint->term)->toBe('shoes');
    expect($touchpoint->content)->toBe('ad1');
    expect($touchpoint->metadata)->toBe(['key' => 'value']);
});
