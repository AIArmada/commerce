<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Customers\Enums\CustomerStatus;
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

    $addresses = $customer->addresses()
        ->get()
        ->sortBy(fn (Address $address): string => (string) $address->pivot?->type)
        ->values()
        ->map(function (Address $address): array {
            return [
                'type' => $address->pivot?->type,
                'recipient_name' => $address->metadata['recipient_name'] ?? null,
                'company' => $address->metadata['company'] ?? null,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'state' => $address->state,
                'postcode' => $address->postcode,
                'country_code' => $address->country_code,
                'is_primary' => (bool) $address->pivot?->is_primary,
            ];
        })
        ->all();

    expect($addresses)->toBe([
        [
            'type' => 'billing',
            'recipient_name' => 'Ada Lovelace',
            'company' => 'Analytical Engines',
            'line1' => '1 Logic Lane',
            'line2' => 'Suite 2',
            'city' => 'Kuala Lumpur',
            'state' => 'WP',
            'postcode' => '50000',
            'country_code' => 'MY',
            'is_primary' => true,
        ],
        [
            'type' => 'shipping',
            'recipient_name' => 'Ada Lovelace',
            'company' => 'Analytical Engines',
            'line1' => '2 Algorithm Avenue',
            'line2' => null,
            'city' => 'Petaling Jaya',
            'state' => 'Selangor',
            'postcode' => '46000',
            'country_code' => 'MY',
            'is_primary' => true,
        ],
    ]);
});
