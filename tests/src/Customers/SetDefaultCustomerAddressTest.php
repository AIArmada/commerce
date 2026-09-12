<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Customers\Actions\SetDefaultCustomerAddress;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;

beforeEach(function (): void {
    $this->customer = Customer::create([
        'first_name' => 'Default',
        'last_name' => 'Address',
        'status' => CustomerStatus::Active,
    ]);
});

test('default address action changes only the requested primary type', function (): void {
    $billingAddress = Address::create([
        'line1' => '1 Billing Street',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country_code' => 'MY',
    ]);
    $shippingAddress = Address::create([
        'line1' => '2 Shipping Street',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country_code' => 'MY',
    ]);
    $replacement = Address::create([
        'line1' => '3 Replacement Street',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country_code' => 'MY',
    ]);

    $this->customer->attachAddress($billingAddress, type: 'billing', isPrimary: true);
    $this->customer->attachAddress($shippingAddress, type: 'shipping', isPrimary: true);
    $this->customer->attachAddress($replacement, type: 'billing');

    app(SetDefaultCustomerAddress::class)->execute($this->customer, $replacement, 'billing');

    $freshCustomer = $this->customer->fresh();

    expect($freshCustomer?->primaryAddress('billing')?->is($replacement))->toBeTrue()
        ->and($freshCustomer?->primaryAddress('shipping')?->is($shippingAddress))->toBeTrue();
});

test('default address action attaches a persisted address', function (): void {
    $address = Address::create([
        'line1' => '1 Shipping Street',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country_code' => 'MY',
    ]);

    app(SetDefaultCustomerAddress::class)->execute($this->customer, $address, 'shipping');

    expect($this->customer->fresh()?->primaryAddress('shipping')?->is($address))->toBeTrue();
});

test('default address action rejects unsaved addresses', function (): void {
    $address = new Address([
        'line1' => 'Unsaved Street',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country_code' => 'MY',
    ]);

    expect(fn (): mixed => app(SetDefaultCustomerAddress::class)->execute($this->customer, $address, 'shipping'))
        ->toThrow(LogicException::class, 'Only persisted addresses can be made default.');
});
