<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
        'affiliate_attribution_id' => (string) Str::uuid(),
        'affiliate_id' => (string) Str::uuid(),
        'affiliate_code' => 'AFF1',
        'subject_type' => 'event',
        'subject_identifier' => 'event:ramadan-1',
        'subject_instance' => 'share',
        'subject_title_snapshot' => 'Ramadan Night',
        'source' => 'google',
        'medium' => 'cpc',
        'campaign' => 'summer',
        'term' => 'shoes',
        'content' => 'ad1',
        'metadata' => ['key' => 'value'],
    ]);

    expect($touchpoint->affiliate_attribution_id)->toBeString();
    expect($touchpoint->affiliate_id)->toBeString();
    expect($touchpoint->affiliate_code)->toBe('AFF1');
    expect($touchpoint->subject_type)->toBe('event');
    expect($touchpoint->subject_identifier)->toBe('event:ramadan-1');
    expect($touchpoint->subject_instance)->toBe('share');
    expect($touchpoint->subject_title_snapshot)->toBe('Ramadan Night');
    expect($touchpoint->source)->toBe('google');
    expect($touchpoint->medium)->toBe('cpc');
    expect($touchpoint->campaign)->toBe('summer');
    expect($touchpoint->term)->toBe('shoes');
    expect($touchpoint->content)->toBe('ad1');
    expect($touchpoint->metadata)->toBe(['key' => 'value']);
});
