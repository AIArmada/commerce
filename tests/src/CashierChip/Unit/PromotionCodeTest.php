<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Coupon;
use AIArmada\CashierChip\PromotionCode;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\VoucherStatus;

class PromotionCodeTest extends CashierChipTestCase
{
    public function test_it_can_be_instantiated()
    {
        $voucher = new VoucherData(
            id: 'uuid',
            code: 'TESTCODE',
            name: 'Test Coupon',
            description: null,
            type: VoucherType::Percentage,
            value: 1000,
            valueConfig: null,
            creditDestination: null,
            creditDelayHours: 0,
            currency: 'MYR',
            minCartValue: null,
            maxDiscount: null,
            usageLimit: null,
            usageLimitPerUser: null,
            allowsManualRedemption: true,
            ownerId: null,
            ownerType: null,
            startsAt: null,
            expiresAt: null,
            status: VoucherStatus::fromString(Active::class),
            targetDefinition: null,
            metadata: []
        );

        $coupon = new Coupon($voucher);
        $promo = new PromotionCode('PROMO123', $coupon);

        $this->assertEquals('PROMO123', $promo->id());
        $this->assertEquals('PROMO123', $promo->code());
        $this->assertSame($coupon, $promo->coupon());
        $this->assertTrue($promo->isActive());
    }

    public function test_magic_get()
    {
        $voucher = new VoucherData(
            id: 'uuid',
            code: 'TESTCODE',
            name: 'Test Coupon',
            description: null,
            type: VoucherType::Percentage,
            value: 1000,
            valueConfig: null,
            creditDestination: null,
            creditDelayHours: 0,
            currency: 'MYR',
            minCartValue: null,
            maxDiscount: null,
            usageLimit: null,
            usageLimitPerUser: null,
            allowsManualRedemption: true,
            ownerId: null,
            ownerType: null,
            startsAt: null,
            expiresAt: null,
            status: VoucherStatus::fromString(Active::class),
            targetDefinition: null,
            metadata: []
        );
        $coupon = new Coupon($voucher);
        $promo = new PromotionCode('PROMO123', $coupon);

        $this->assertEquals('PROMO123', $promo->id);
        $this->assertEquals('PROMO123', $promo->code);
        $this->assertSame($coupon, $promo->coupon);
        $this->assertTrue($promo->active);
    }

    public function test_serialization()
    {
        $voucher = new VoucherData(
            id: 'uuid',
            code: 'TESTCODE',
            name: 'Test Coupon',
            description: null,
            type: VoucherType::Percentage,
            value: 1000,
            valueConfig: null,
            creditDestination: null,
            creditDelayHours: 0,
            currency: 'MYR',
            minCartValue: null,
            maxDiscount: null,
            usageLimit: null,
            usageLimitPerUser: null,
            allowsManualRedemption: true,
            ownerId: null,
            ownerType: null,
            startsAt: null,
            expiresAt: null,
            status: VoucherStatus::fromString(Active::class),
            targetDefinition: null,
            metadata: []
        );
        $coupon = new Coupon($voucher);
        $promo = new PromotionCode('PROMO123', $coupon);

        $array = $promo->toArray();
        $this->assertEquals('PROMO123', $array['code']);
        $this->assertTrue($array['active']);

        $json = $promo->toJson();
        $this->assertJson($json);
    }
}
