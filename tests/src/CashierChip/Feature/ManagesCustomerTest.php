<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Feature;

use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

class ManagesCustomerTest extends CashierChipTestCase
{
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
    }

    public function test_create_as_chip_customer()
    {
        $customer = $this->user->createAsChipCustomer([
            'email' => 'new@example.com',
            'full_name' => 'John Doe',
        ]);

        $this->assertEquals('new@example.com', $customer->email);
        $this->assertEquals('John Doe', $customer->full_name);
        $this->assertNotNull($this->user->chip_id);
    }

    public function test_update_chip_customer()
    {
        $this->user->createAsChipCustomer();
        $originalId = $this->user->chip_id;

        $customer = $this->user->updateChipCustomer(['full_name' => 'Updated Name']);

        $this->assertEquals('Updated Name', $customer->full_name);
        $this->assertEquals($originalId, $this->user->chip_id);
    }

    public function test_as_chip_customer()
    {
        $this->user->createAsChipCustomer();
        $customer = $this->user->asChipCustomer();

        $this->assertEquals($this->user->chip_id, $customer->id);
    }

    public function test_chip_name_and_email_accessors()
    {
        $this->user->name = 'Test User';
        $this->user->email = 'test@example.com';

        $this->assertEquals('Test User', $this->user->chipName());
        $this->assertEquals('test@example.com', $this->user->chipEmail());
    }
}
