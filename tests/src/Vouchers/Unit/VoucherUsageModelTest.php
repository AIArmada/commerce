<?php

declare(strict_types=1);

use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function createVoucherForUsageTest(string $code = 'USAGE-TEST'): Voucher
{
    return Voucher::create([
        'code' => $code,
        'name' => 'Test Voucher for Usage',
        'type' => VoucherType::Percentage,
        'value' => 1000,
    ]);
}

describe('VoucherUsage Model', function (): void {
    it('creates usage record with correct attributes', function (): void {
        $voucher = createVoucherForUsageTest();
        $usedAt = Carbon::now();

        $usage = VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 1500,
            'currency' => 'MYR',
            'channel' => VoucherUsage::CHANNEL_AUTOMATIC,
            'used_at' => $usedAt,
        ]);

        expect($usage)->toBeInstanceOf(VoucherUsage::class)
            ->and($usage->voucher_id)->toBe($voucher->id)
            ->and($usage->discount_amount)->toBe(1500)
            ->and($usage->currency)->toBe('MYR')
            ->and($usage->channel)->toBe('automatic');
    });

    it('has correct channel constants', function (): void {
        expect(VoucherUsage::CHANNEL_AUTOMATIC)->toBe('automatic')
            ->and(VoucherUsage::CHANNEL_MANUAL)->toBe('manual')
            ->and(VoucherUsage::CHANNEL_API)->toBe('api');
    });

    it('belongs to voucher', function (): void {
        $voucher = createVoucherForUsageTest('RELATION-USAGE');
        $usage = VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 500,
            'currency' => 'MYR',
            'channel' => VoucherUsage::CHANNEL_API,
            'used_at' => now(),
        ]);

        expect($usage->voucher)->toBeInstanceOf(Voucher::class)
            ->and($usage->voucher->id)->toBe($voucher->id)
            ->and($usage->voucher->code)->toBe('RELATION-USAGE');
    });

    it('identifies manual channel', function (): void {
        $voucher = createVoucherForUsageTest('MANUAL-TEST');

        $manualUsage = VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 500,
            'currency' => 'MYR',
            'channel' => VoucherUsage::CHANNEL_MANUAL,
            'used_at' => now(),
        ]);

        $automaticUsage = VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 500,
            'currency' => 'MYR',
            'channel' => VoucherUsage::CHANNEL_AUTOMATIC,
            'used_at' => now(),
        ]);

        expect($manualUsage->isManual())->toBeTrue()
            ->and($automaticUsage->isManual())->toBeFalse();
    });

    it('casts attributes correctly', function (): void {
        $voucher = createVoucherForUsageTest('CASTS-TEST');
        $usage = VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 1000,
            'currency' => 'MYR',
            'channel' => VoucherUsage::CHANNEL_AUTOMATIC,
            'metadata' => ['order_id' => 'order-123', 'cart_id' => 'cart-456'],
            'target_definition' => ['product_ids' => ['prod-1', 'prod-2']],
            'used_at' => '2024-01-15 10:30:00',
        ]);

        expect($usage->discount_amount)->toBeInt()
            ->and($usage->metadata)->toBeArray()
            ->and($usage->metadata)->toHaveKey('order_id')
            ->and($usage->target_definition)->toBeArray()
            ->and($usage->used_at)->toBeInstanceOf(Carbon::class);
    });

    it('stores notes correctly', function (): void {
        $voucher = createVoucherForUsageTest('NOTES-TEST');
        $usage = VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 750,
            'currency' => 'MYR',
            'channel' => VoucherUsage::CHANNEL_MANUAL,
            'notes' => 'Customer service approved this discount',
            'used_at' => now(),
        ]);

        expect($usage->notes)->toBe('Customer service approved this discount');
    });

    it('uses correct table name from config', function (): void {
        $usage = new VoucherUsage;
        $table = $usage->getTable();

        expect($table)->toBe('voucher_usage');
    });

    it('returns N/A for user identifier when no redeemed by', function (): void {
        $voucher = createVoucherForUsageTest('NO-USER-TEST');
        $usage = VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 500,
            'currency' => 'MYR',
            'channel' => VoucherUsage::CHANNEL_AUTOMATIC,
            'used_at' => now(),
        ]);

        expect($usage->user_identifier)->toBe('N/A');
    });

    it('has timestamps disabled', function (): void {
        $usage = new VoucherUsage;

        expect($usage->timestamps)->toBeFalse();
    });

    it('allows polymorphic redeemedBy relationship', function (): void {
        $voucher = createVoucherForUsageTest('MORPH-TEST');
        $usage = VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 500,
            'currency' => 'MYR',
            'channel' => VoucherUsage::CHANNEL_API,
            'redeemed_by_type' => 'user',
            'redeemed_by_id' => 'user-123',
            'used_at' => now(),
        ]);

        expect($usage->redeemed_by_type)->toBe('user')
            ->and($usage->redeemed_by_id)->toBe('user-123');
    });
});

describe('VoucherUsage with Voucher Relationship', function (): void {
    it('can be created and retrieved through voucher', function (): void {
        $voucher = createVoucherForUsageTest('RETRIEVE-TEST');

        VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 500,
            'currency' => 'MYR',
            'channel' => VoucherUsage::CHANNEL_AUTOMATIC,
            'used_at' => now(),
        ]);

        VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 750,
            'currency' => 'MYR',
            'channel' => VoucherUsage::CHANNEL_API,
            'used_at' => now(),
        ]);

        $voucher->refresh();
        $usages = $voucher->usages;

        expect($usages)->toHaveCount(2)
            ->and($usages->sum('discount_amount'))->toBe(1250);
    });

    it('correctly tracks multiple usages', function (): void {
        $voucher = createVoucherForUsageTest('MULTI-USAGE');

        for ($i = 0; $i < 5; $i++) {
            VoucherUsage::create([
                'voucher_id' => $voucher->id,
                'discount_amount' => 100 * ($i + 1),
                'currency' => 'MYR',
                'channel' => VoucherUsage::CHANNEL_AUTOMATIC,
                'used_at' => now()->addMinutes($i),
            ]);
        }

        $voucher->refresh();

        expect($voucher->usages)->toHaveCount(5)
            ->and($voucher->usages->sum('discount_amount'))->toBe(1500);
    });
});
