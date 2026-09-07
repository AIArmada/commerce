<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Discount;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Carbon\Carbon;
use Carbon\CarbonInterface;

uses(CashierChipTestCase::class);

describe('Discount', function (): void {
    it('can create discount', function (): void {
        $discount = new Discount(['amount' => 1000]);

        $this->assertInstanceOf(Discount::class, $discount);
    });

    it('dynamic property access', function (): void {
        $discount = new Discount(['amount' => 1000, 'some_key' => 'some_value']);

        $this->assertEquals(1000, $discount->amount);
        $this->assertEquals('some_value', $discount->some_key);
    });

    it('amount', function (): void {
        $discount = new Discount(['amount' => 1000]);

        $this->assertEquals(1000, $discount->amount());
    });

    it('amount null', function (): void {
        $discount = new Discount([]);

        $this->assertNull($discount->amount());
    });

    it('formatted amount', function (): void {
        $discount = new Discount(['amount' => 1000, 'currency' => 'MYR']);

        $formatted = $discount->formattedAmount();

        $this->assertIsString($formatted);
    });

    it('formatted amount null', function (): void {
        $discount = new Discount([]);

        $this->assertNull($discount->formattedAmount());
    });

    it('coupon returns null when not set', function (): void {
        $discount = new Discount([]);

        $this->assertNull($discount->coupon());
    });

    it('promotion code returns null when not set', function (): void {
        $discount = new Discount([]);

        $this->assertNull($discount->promotionCode());
    });

    it('start returns null when not set', function (): void {
        $discount = new Discount([]);

        $this->assertNull($discount->start());
    });

    it('start with carbon instance', function (): void {
        $date = Carbon::now();
        $discount = new Discount(['start' => $date]);

        $this->assertSame($date, $discount->start());
    });

    it('start with timestamp', function (): void {
        $timestamp = Carbon::now()->timestamp;
        $discount = new Discount(['start' => $timestamp]);

        $this->assertNotNull($discount->start());
        $this->assertInstanceOf(CarbonInterface::class, $discount->start());
    });

    it('start with string', function (): void {
        $discount = new Discount(['start' => '2024-01-01 00:00:00']);

        $this->assertNotNull($discount->start());
        $this->assertInstanceOf(CarbonInterface::class, $discount->start());
    });

    it('end returns null when not set', function (): void {
        $discount = new Discount([]);

        $this->assertNull($discount->end());
    });

    it('end with carbon instance', function (): void {
        $date = Carbon::now()->addDays(30);
        $discount = new Discount(['end' => $date]);

        $this->assertSame($date, $discount->end());
    });

    it('end with timestamp', function (): void {
        $timestamp = Carbon::now()->addDays(30)->timestamp;
        $discount = new Discount(['end' => $timestamp]);

        $this->assertNotNull($discount->end());
        $this->assertInstanceOf(CarbonInterface::class, $discount->end());
    });

    it('end with string', function (): void {
        $discount = new Discount(['end' => '2024-12-31 23:59:59']);

        $this->assertNotNull($discount->end());
        $this->assertInstanceOf(CarbonInterface::class, $discount->end());
    });

    it('to array', function (): void {
        $discount = new Discount(['amount' => 1000]);

        $array = $discount->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('amount', $array);
        $this->assertEquals(1000, $array['amount']);
    });

    it('to json', function (): void {
        $discount = new Discount(['amount' => 1000]);

        $json = $discount->toJson();

        $this->assertJson($json);
    });

    it('json serialize', function (): void {
        $discount = new Discount(['amount' => 1000]);

        $serialized = $discount->jsonSerialize();

        $this->assertIsArray($serialized);
    });
});
