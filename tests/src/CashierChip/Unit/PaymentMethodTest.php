<?php

declare(strict_types=1);

use AIArmada\CashierChip\Payment\PaymentMethod;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use Mockery;

uses(CashierChipTestCase::class);

describe('PaymentMethod', function (): void {
    it('it can instantiate and access properties', function (): void {
        $owner = new User;
        $tokenData = [
            'id' => 'tok_123',
            'type' => 'client_recurring_token',
            'payment_method' => 'visa',
            'description' => '**** **** **** 4242',
            'created_on' => 1704067200,
            'updated_on' => 1704067200,
        ];

        $paymentMethod = new PaymentMethod($owner, $tokenData);

        $this->assertEquals('tok_123', $paymentMethod->id());
        $this->assertNull($paymentMethod->brand());
        $this->assertNull($paymentMethod->lastFour());
        $this->assertNull($paymentMethod->expirationMonth());
        $this->assertNull($paymentMethod->expirationYear());
        $this->assertEquals('visa', $paymentMethod->type());
        $this->assertSame($owner, $paymentMethod->owner());
        $this->assertEquals($tokenData, $paymentMethod->asChipRecurringToken());
        $this->assertSame('tok_123', $paymentMethod->toArray()['id']);
        $this->assertSame('visa', $paymentMethod->toArray()['type']);
        $this->assertEquals(json_encode($paymentMethod->toArray()), $paymentMethod->toJson());

        // Blade aliases
        $this->assertNull($paymentMethod->cardBrand());
        $this->assertNull($paymentMethod->cardLastFour());
        $this->assertNull($paymentMethod->cardExpMonth());
        $this->assertNull($paymentMethod->cardExpYear());
        $this->assertEquals('tok_123', $paymentMethod->chipToken());
    });

    it('it can check is default', function (): void {
        $owner = Mockery::mock(User::class);
        $tokenData = ['id' => 'tok_123'];
        $paymentMethod = new PaymentMethod($owner, $tokenData);

        $owner->shouldReceive('defaultPaymentMethod')->andReturn($paymentMethod);

        $this->assertTrue($paymentMethod->isDefault());

        $otherMethod = new PaymentMethod($owner, ['id' => 'tok_456']);
        $this->assertFalse($otherMethod->isDefault());
    });

    it('it can delete', function (): void {
        $owner = Mockery::mock(User::class);
        $tokenData = ['id' => 'tok_123'];
        $paymentMethod = new PaymentMethod($owner, $tokenData);

        $owner->shouldReceive('deletePaymentMethod')->with('tok_123')->once();

        $paymentMethod->delete();
        $this->assertTrue(true);
    });
});
