<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Widgets\VoucherUsageTimelineWidget;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;

uses(TestCase::class);

function timelineVoucher(): Voucher
{
    return Voucher::query()->create([
        'code' => 'TL-' . mb_strtoupper(Str::random(6)),
        'name' => 'Timeline',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'MYR',
        'starts_at' => now()->subDay(),
    ]);
}

function recordTimelineUsage(Voucher $voucher, int $amount, ?User $user, int $minutesAgo): void
{
    VoucherUsage::query()->create([
        'voucher_id' => $voucher->id,
        'discount_amount' => $amount,
        'currency' => 'MYR',
        'channel' => VoucherUsage::CHANNEL_API,
        'redeemed_by_type' => $user?->getMorphClass(),
        'redeemed_by_id' => $user?->getKey(),
        'used_at' => now()->subMinutes($minutesAgo),
    ]);
}

it('summarizes usage with aggregates instead of hydrating every row', function (): void {
    $voucher = timelineVoucher();
    $anna = User::query()->create(['name' => 'Anna', 'email' => 'anna-timeline@example.com', 'password' => 'secret']);
    $bob = User::query()->create(['name' => 'Bob', 'email' => 'bob-timeline@example.com', 'password' => 'secret']);

    recordTimelineUsage($voucher, 50000, $anna, 30);
    recordTimelineUsage($voucher, 30000, $anna, 20);
    recordTimelineUsage($voucher, 20000, $bob, 10);

    $widget = app(VoucherUsageTimelineWidget::class);
    $widget->record = $voucher;

    $stats = $widget->getSummaryStats();

    expect($stats['total_redemptions'])->toBe(3)
        ->and($stats['unique_customers'])->toBe(2)
        ->and($stats['total_savings'])->toContain('1,000');
});

it('caps the rendered timeline at fifty events', function (): void {
    $voucher = timelineVoucher();
    $user = User::query()->create(['name' => 'Zed', 'email' => 'zed-timeline@example.com', 'password' => 'secret']);

    for ($i = 0; $i < 60; $i++) {
        recordTimelineUsage($voucher, 100, $user, $i);
    }

    $widget = app(VoucherUsageTimelineWidget::class);
    $widget->record = $voucher;

    expect($widget->getTimelineEvents())->toHaveCount(50)
        ->and($widget->getSummaryStats()['total_redemptions'])->toBe(60);
});
