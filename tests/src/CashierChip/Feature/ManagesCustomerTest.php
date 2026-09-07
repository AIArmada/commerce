<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('ManagesCustomer', function (): void {
    beforeEach(function (): void {
        $this->user = $this->createUser();
    });

    it('create as chip customer', function (): void {
        $customer = $this->user->createAsChipCustomer([
            'email' => 'new@example.com',
            'full_name' => 'John Doe',
        ]);

        $this->assertEquals('new@example.com', $customer->email);
        $this->assertEquals('John Doe', $customer->full_name);
        $this->assertNotNull($this->user->chip_id);
    });

    it('update chip customer', function (): void {
        $this->user->createAsChipCustomer();
        $originalId = $this->user->chip_id;

        $customer = $this->user->updateChipCustomer(['full_name' => 'Updated Name']);

        $this->assertEquals('Updated Name', $customer->full_name);
        $this->assertEquals($originalId, $this->user->chip_id);
    });

    it('as chip customer', function (): void {
        $this->user->createAsChipCustomer();
        $customer = $this->user->asChipCustomer();

        $this->assertEquals($this->user->chip_id, $customer->id);
    });

    it('chip name and email accessors', function (): void {
        $this->user->name = 'Test User';
        $this->user->email = 'test@example.com';

        $this->assertEquals('Test User', $this->user->chipName());
        $this->assertEquals('test@example.com', $this->user->chipEmail());
    });
});
