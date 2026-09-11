<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\AddressType;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Address;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Services\CustomerResolver;

/**
 * @return array{billing: array<string, mixed>, shipping: array<string, mixed>}
 */
function customerDecompositionFixture(): array
{
    return [
        'billing' => [
            'email' => 'decomposition@example.com',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'company' => 'Analytical Engines',
            'line1' => '1 Logic Lane',
            'line2' => 'Suite 2',
            'city' => 'Kuala Lumpur',
            'state' => 'WP',
            'postcode' => '50000',
            'country' => 'my',
        ],
        'shipping' => [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'company' => 'Analytical Engines',
            'line1' => '2 Algorithm Avenue',
            'city' => 'Petaling Jaya',
            'state' => 'Selangor',
            'postcode' => '46000',
            'country_code' => 'my',
        ],
    ];
}

test('customer decomposition preserves the resolver fixture output exactly', function (): void {
    $fixture = customerDecompositionFixture();
    $customer = app(CustomerResolver::class)->resolve(
        user: null,
        sessionCustomer: null,
        billingData: $fixture['billing'],
        shippingData: $fixture['shipping'],
    );

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->only(['first_name', 'last_name', 'company', 'is_guest']))->toBe([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'company' => 'Analytical Engines',
            'is_guest' => true,
        ])
        ->and($customer->status)->toBe(CustomerStatus::Active)
        ->and($customer->getCasts()['status'])->toBe(CustomerStatus::class);

    $addresses = $customer->legacyAddresses()
        ->orderBy('type')
        ->get()
        ->map(function (Address $address): array {
            $attributes = $address->only([
                'type',
                'recipient_name',
                'company',
                'line1',
                'line2',
                'city',
                'state',
                'postcode',
                'country_code',
                'is_default_billing',
                'is_default_shipping',
            ]);
            $attributes['type'] = $address->type->value;

            return $attributes;
        })
        ->all();

    expect($addresses)->toBe([
        [
            'type' => AddressType::Billing->value,
            'recipient_name' => 'Ada Lovelace',
            'company' => 'Analytical Engines',
            'line1' => '1 Logic Lane',
            'line2' => 'Suite 2',
            'city' => 'Kuala Lumpur',
            'state' => 'WP',
            'postcode' => '50000',
            'country_code' => 'MY',
            'is_default_billing' => true,
            'is_default_shipping' => false,
        ],
        [
            'type' => AddressType::Shipping->value,
            'recipient_name' => 'Ada Lovelace',
            'company' => 'Analytical Engines',
            'line1' => '2 Algorithm Avenue',
            'line2' => null,
            'city' => 'Petaling Jaya',
            'state' => 'Selangor',
            'postcode' => '46000',
            'country_code' => 'MY',
            'is_default_billing' => false,
            'is_default_shipping' => true,
        ],
    ]);
});
