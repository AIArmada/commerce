<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;

test('AffiliatePayoutEvent has correct fillable attributes', function (): void {
    $event = AffiliatePayoutEvent::create([
        'affiliate_payout_id' => 1,
        'from_status' => 'pending',
        'to_status' => 'approved',
        'metadata' => ['key' => 'value'],
        'notes' => 'Test notes',
    ]);

    expect($event->affiliate_payout_id)->toBe(1);
    expect($event->from_status)->toBe('pending');
    expect($event->to_status)->toBe('approved');
    expect($event->metadata)->toBe(['key' => 'value']);
    expect($event->notes)->toBe('Test notes');
});

test('AffiliatePayoutEvent has metadata attribute', function (): void {
    $event = AffiliatePayoutEvent::create([
        'affiliate_payout_id' => 1,
        'from_status' => 'pending',
        'to_status' => 'approved',
        'metadata' => ['test' => 'value'],
    ]);

    expect($event->metadata)->toBe(['test' => 'value']);
});

test('AffiliatePayoutEvent has payout relationship', function (): void {
    $payout = AffiliatePayout::create([
        'reference' => 'PAY123',
        'affiliate_id' => 1,
        'total_commission_minor' => 1000,
        'currency' => 'USD',
        'status' => 'pending',
    ]);

    $event = AffiliatePayoutEvent::create([
        'affiliate_payout_id' => $payout->id,
        'from_status' => 'pending',
        'to_status' => 'approved',
    ]);

    expect($event->payout)->toBeInstanceOf(AffiliatePayout::class);
    expect($event->payout->id)->toBe($payout->id);
});