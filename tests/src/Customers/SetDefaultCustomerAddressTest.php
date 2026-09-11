<?php

declare(strict_types=1);

use AIArmada\Customers\Actions\SetDefaultCustomerAddress;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Address;
use AIArmada\Customers\Models\Customer;

beforeEach(function (): void {
    $this->customer = Customer::create([
        'first_name' => 'Default',
        'last_name' => 'Address',
        'status' => CustomerStatus::Active,
    ]);
});

test('default address action changes only the requested legacy default flag', function (): void {
    $billingAddress = Address::create([
        'customer_id' => $this->customer->id,
        'line1' => '1 Billing Street',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country_code' => 'MY',
        'is_default_billing' => true,
    ]);
    $shippingAddress = Address::create([
        'customer_id' => $this->customer->id,
        'line1' => '2 Shipping Street',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country_code' => 'MY',
        'is_default_shipping' => true,
    ]);
    $replacement = Address::create([
        'customer_id' => $this->customer->id,
        'line1' => '3 Replacement Street',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country_code' => 'MY',
    ]);

    app(SetDefaultCustomerAddress::class)->execute($replacement, 'billing');

    expect($replacement->fresh()->is_default_billing)->toBeTrue()
        ->and($billingAddress->fresh()->is_default_billing)->toBeFalse()
        ->and($shippingAddress->fresh()->is_default_shipping)->toBeTrue()
        ->and($replacement->fresh()->is_default_shipping)->toBeFalse();
});

test('default address action rejects unsaved addresses', function (): void {
    $address = new Address([
        'customer_id' => $this->customer->id,
        'line1' => 'Unsaved Street',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country_code' => 'MY',
    ]);

    expect(fn () => app(SetDefaultCustomerAddress::class)->execute($address, 'shipping'))
        ->toThrow(LogicException::class, 'Only persisted addresses can be made default.');
});
