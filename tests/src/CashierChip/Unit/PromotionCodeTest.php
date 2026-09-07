<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Coupon;
use AIArmada\CashierChip\Billing\PromotionCode;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\VoucherStatus;

uses(CashierChipTestCase::class);

describe('PromotionCode', function (): void {
    it('it can be instantiated', function (): void {
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
    });

    it('magic get', function (): void {
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
    });

    it('serialization', function (): void {
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
    });
});
