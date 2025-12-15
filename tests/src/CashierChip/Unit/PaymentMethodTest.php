<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\PaymentMethod;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use Mockery;

class PaymentMethodTest extends CashierChipTestCase
{
    public function test_it_can_instantiate_and_access_properties()
    {
        $owner = new User;
        $tokenData = [
            'id' => 'tok_123',
            'recurring_token' => 'tok_123',
            'card_brand' => 'Visa',
            'brand' => 'Visa',
            'last_4' => '4242',
            'card_last_4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'type' => 'card',
        ];

        $paymentMethod = new PaymentMethod($owner, $tokenData);

        $this->assertEquals('tok_123', $paymentMethod->id());
        $this->assertEquals('Visa', $paymentMethod->brand());
        $this->assertEquals('4242', $paymentMethod->lastFour());
        $this->assertEquals(12, $paymentMethod->expirationMonth());
        $this->assertEquals(2030, $paymentMethod->expirationYear());
        $this->assertEquals('card', $paymentMethod->type());
        $this->assertSame($owner, $paymentMethod->owner());
        $this->assertEquals($tokenData, $paymentMethod->asChipRecurringToken());
        $this->assertEquals($tokenData, $paymentMethod->toArray());
        $this->assertEquals(json_encode($tokenData), $paymentMethod->toJson());

        // Blade aliases
        $this->assertEquals('Visa', $paymentMethod->cardBrand());
        $this->assertEquals('4242', $paymentMethod->cardLastFour());
        $this->assertEquals(12, $paymentMethod->cardExpMonth());
        $this->assertEquals(2030, $paymentMethod->cardExpYear());
        $this->assertEquals('tok_123', $paymentMethod->chipToken());
    }

    public function test_it_can_check_is_default()
    {
        $owner = Mockery::mock(User::class);
        $tokenData = ['id' => 'tok_123'];
        $paymentMethod = new PaymentMethod($owner, $tokenData);

        $owner->shouldReceive('defaultPaymentMethod')->andReturn($paymentMethod);

        $this->assertTrue($paymentMethod->isDefault());

        $otherMethod = new PaymentMethod($owner, ['id' => 'tok_456']);
        $this->assertFalse($otherMethod->isDefault());
    }

    public function test_it_can_delete()
    {
        $owner = Mockery::mock(User::class);
        $tokenData = ['id' => 'tok_123'];
        $paymentMethod = new PaymentMethod($owner, $tokenData);

        $owner->shouldReceive('deletePaymentMethod')->with('tok_123')->once();

        $paymentMethod->delete();
        $this->assertTrue(true);
    }
}
