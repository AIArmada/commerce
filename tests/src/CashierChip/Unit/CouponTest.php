<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Coupon;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Paused;
use AIArmada\Vouchers\States\VoucherStatus;
use Carbon\Carbon;

class CouponTest extends CashierChipTestCase
{
    public function test_it_can_get_attributes()
    {
        $voucher = $this->createVoucherData([
            'metadata' => ['duration' => 'once'],
        ]);
        $coupon = new Coupon($voucher);

        $this->assertEquals('TEST_CODE', $coupon->id());
        $this->assertEquals('Test Name', $coupon->name());
        $this->assertEquals('MYR', $coupon->currency());
        $this->assertEquals('once', $coupon->duration());
    }

    public function test_magic_get_method()
    {
        $voucher = $this->createVoucherData([
            'metadata' => ['duration' => 'repeating'],
        ]);
        $coupon = new Coupon($voucher);

        $this->assertEquals('TEST_CODE', $coupon->id);
        $this->assertEquals('Test Name', $coupon->name);
        $this->assertEquals('MYR', $coupon->currency);
        $this->assertEquals('repeating', $coupon->duration);
        $this->assertEquals(10.0, $coupon->percent_off);
    }

    public function test_percentage_coupon()
    {
        $voucher = $this->createVoucherData([
            'type' => VoucherType::Percentage,
            'value' => 2000, // 20%
        ]);
        $coupon = new Coupon($voucher);

        $this->assertTrue($coupon->isPercentage());
        $this->assertEquals(20.0, $coupon->percentOff());
        $this->assertNull($coupon->amountOff());
        $this->assertNull($coupon->rawAmountOff());
    }

    public function test_fixed_amount_coupon()
    {
        $voucher = $this->createVoucherData([
            'type' => VoucherType::Fixed,
            'value' => 500, // 5.00
            'metadata' => ['duration' => 'forever'],
        ]);
        $coupon = new Coupon($voucher);

        $this->assertFalse($coupon->isPercentage());
        $this->assertNull($coupon->percentOff());
        $this->assertEquals(500, $coupon->rawAmountOff());
        $this->assertTrue($coupon->isForeverAmountOff());
    }

    public function test_duration_in_months()
    {
        $voucher = $this->createVoucherData([
            'metadata' => ['duration' => 'repeating', 'duration_in_months' => 3],
        ]);
        $coupon = new Coupon($voucher);

        $this->assertEquals('repeating', $coupon->duration());
        $this->assertEquals(3, $coupon->durationInMonths());
    }

    public function test_validation()
    {
        $activeVoucher = $this->createVoucherData([
            'status' => VoucherStatus::fromString(Active::class),
            'startsAt' => Carbon::yesterday(),
            'expiresAt' => Carbon::tomorrow(),
        ]);
        $this->assertTrue((new Coupon($activeVoucher))->isValid());
        $this->assertTrue((new Coupon($activeVoucher))->isActive());
        $this->assertFalse((new Coupon($activeVoucher))->isExpired());

        $inactiveVoucher = $this->createVoucherData(['status' => VoucherStatus::fromString(Paused::class)]);
        $this->assertFalse((new Coupon($inactiveVoucher))->isValid());
        $this->assertFalse((new Coupon($inactiveVoucher))->isActive());

        $futureVoucher = $this->createVoucherData(['startsAt' => Carbon::tomorrow()]);
        $this->assertFalse((new Coupon($futureVoucher))->isValid());

        $expiredVoucher = $this->createVoucherData(['expiresAt' => Carbon::yesterday()]);
        $this->assertFalse((new Coupon($expiredVoucher))->isValid());
        $this->assertTrue((new Coupon($expiredVoucher))->isExpired());
    }

    public function test_calculate_discount_percentage()
    {
        $voucher = $this->createVoucherData([
            'type' => VoucherType::Percentage,
            'value' => 1000, // 10%
        ]);
        $coupon = new Coupon($voucher);

        // 10% of 10000 = 1000
        $this->assertEquals(1000, $coupon->calculateDiscount(10000));
    }

    public function test_calculate_discount_fixed()
    {
        $voucher = $this->createVoucherData([
            'status' => VoucherStatus::fromString(Active::class),
            'type' => VoucherType::Fixed,
            'value' => 500, // 5.00
        ]);
        $coupon = new Coupon($voucher);

        $this->assertEquals(500, $coupon->calculateDiscount(10000));
        // Capped at amount
        $this->assertEquals(400, $coupon->calculateDiscount(400));
        $inactiveVoucher = $this->createVoucherData(['status' => VoucherStatus::fromString(Paused::class)]);

        $inactiveCoupon = new Coupon($inactiveVoucher);
        $this->assertFalse($inactiveCoupon->isValid());
    }

    public function test_calculate_discount_with_min_cart_value()
    {
        $voucher = $this->createVoucherData([
            'type' => VoucherType::Fixed,
            'value' => 500,
            'minCartValue' => 2000,
        ]);
        $coupon = new Coupon($voucher);

        $this->assertEquals(0, $coupon->calculateDiscount(1000));
        $this->assertEquals(500, $coupon->calculateDiscount(2000));
    }

    public function test_calculate_discount_free_shipping()
    {
        $voucher = $this->createVoucherData([
            'type' => VoucherType::FreeShipping,
        ]);
        $coupon = new Coupon($voucher);

        $this->assertEquals(0, $coupon->calculateDiscount(10000));
    }

    public function test_serialization()
    {
        $voucher = $this->createVoucherData();
        $coupon = new Coupon($voucher);

        $array = $coupon->toArray();
        $json = $coupon->toJson();
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);

        $this->assertJson($json);
    }

    protected function createVoucherData(array $attributes = []): VoucherData
    {
        return new VoucherData(
            id: $attributes['id'] ?? 'test_id',
            code: $attributes['code'] ?? 'TEST_CODE',
            name: $attributes['name'] ?? 'Test Name',
            description: $attributes['description'] ?? null,
            type: $attributes['type'] ?? VoucherType::Percentage,
            value: $attributes['value'] ?? 1000,
            valueConfig: null,
            creditDestination: null,
            creditDelayHours: 0,
            currency: $attributes['currency'] ?? 'MYR',
            minCartValue: $attributes['minCartValue'] ?? null,
            maxDiscount: $attributes['maxDiscount'] ?? null,
            usageLimit: null,
            usageLimitPerUser: null,
            allowsManualRedemption: true,
            ownerId: null,
            ownerType: null,
            startsAt: $attributes['startsAt'] ?? null,
            expiresAt: $attributes['expiresAt'] ?? null,
            status: $attributes['status'] ?? VoucherStatus::fromString(Active::class),
            targetDefinition: null,
            metadata: $attributes['metadata'] ?? [],
        );
    }
}
