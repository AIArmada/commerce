<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Discount;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Carbon\Carbon;

class DiscountTest extends CashierChipTestCase
{
    public function test_can_create_discount(): void
    {
        $discount = new Discount(['amount' => 1000]);

        $this->assertInstanceOf(Discount::class, $discount);
    }

    public function test_dynamic_property_access(): void
    {
        $discount = new Discount(['amount' => 1000, 'some_key' => 'some_value']);

        $this->assertEquals(1000, $discount->amount);
        $this->assertEquals('some_value', $discount->some_key);
    }

    public function test_amount(): void
    {
        $discount = new Discount(['amount' => 1000]);

        $this->assertEquals(1000, $discount->amount());
    }

    public function test_amount_null(): void
    {
        $discount = new Discount([]);

        $this->assertNull($discount->amount());
    }

    public function test_formatted_amount(): void
    {
        $discount = new Discount(['amount' => 1000, 'currency' => 'MYR']);

        $formatted = $discount->formattedAmount();

        $this->assertIsString($formatted);
    }

    public function test_formatted_amount_null(): void
    {
        $discount = new Discount([]);

        $this->assertNull($discount->formattedAmount());
    }

    public function test_coupon_returns_null_when_not_set(): void
    {
        $discount = new Discount([]);

        $this->assertNull($discount->coupon());
    }

    public function test_promotion_code_returns_null_when_not_set(): void
    {
        $discount = new Discount([]);

        $this->assertNull($discount->promotionCode());
    }

    public function test_start_returns_null_when_not_set(): void
    {
        $discount = new Discount([]);

        $this->assertNull($discount->start());
    }

    public function test_start_with_carbon_instance(): void
    {
        $date = Carbon::now();
        $discount = new Discount(['start' => $date]);

        $this->assertSame($date, $discount->start());
    }

    public function test_start_with_timestamp(): void
    {
        $timestamp = Carbon::now()->timestamp;
        $discount = new Discount(['start' => $timestamp]);

        $this->assertNotNull($discount->start());
        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $discount->start());
    }

    public function test_start_with_string(): void
    {
        $discount = new Discount(['start' => '2024-01-01 00:00:00']);

        $this->assertNotNull($discount->start());
        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $discount->start());
    }

    public function test_end_returns_null_when_not_set(): void
    {
        $discount = new Discount([]);

        $this->assertNull($discount->end());
    }

    public function test_end_with_carbon_instance(): void
    {
        $date = Carbon::now()->addDays(30);
        $discount = new Discount(['end' => $date]);

        $this->assertSame($date, $discount->end());
    }

    public function test_end_with_timestamp(): void
    {
        $timestamp = Carbon::now()->addDays(30)->timestamp;
        $discount = new Discount(['end' => $timestamp]);

        $this->assertNotNull($discount->end());
        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $discount->end());
    }

    public function test_end_with_string(): void
    {
        $discount = new Discount(['end' => '2024-12-31 23:59:59']);

        $this->assertNotNull($discount->end());
        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $discount->end());
    }

    public function test_to_array(): void
    {
        $discount = new Discount(['amount' => 1000]);

        $array = $discount->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('amount', $array);
        $this->assertEquals(1000, $array['amount']);
    }

    public function test_to_json(): void
    {
        $discount = new Discount(['amount' => 1000]);

        $json = $discount->toJson();

        $this->assertJson($json);
    }

    public function test_json_serialize(): void
    {
        $discount = new Discount(['amount' => 1000]);

        $serialized = $discount->jsonSerialize();

        $this->assertIsArray($serialized);
    }
}
