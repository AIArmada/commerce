<?php

declare(strict_types=1);

use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;

beforeEach(function (): void {
    // Owner-disabled on purpose: matchingCustomersQuery() resolves the
    // segment owner through the morph map, which the fake test owner cannot
    // pass. Global rows exercise the same condition-normalization contract.
    config()->set('customers.features.owner.enabled', false);
});

it('honors the adapter-stored value_status condition shape when matching customers', function (): void {
    Customer::query()->create([
        'first_name' => 'Active',
        'last_name' => 'Customer',
        'status' => 'active',
        'accepts_marketing' => false,
    ]);

    // A status rule the active customer does NOT satisfy must match
    // nobody. Before the core fix, value_status was silently skipped and
    // this matched every active customer.
    $suspendedOnly = Segment::query()->create([
        'name' => 'Suspended Only',
        'slug' => 'suspended-only-' . uniqid(),
        'type' => 'custom',
        'is_automatic' => true,
        'is_active' => true,
        'conditions' => [['field' => 'status', 'value_status' => 'suspended']],
    ]);

    expect($suspendedOnly->countMatchingCustomers())->toBe(0);

    $activeOnly = Segment::query()->create([
        'name' => 'Active Only',
        'slug' => 'active-only-' . uniqid(),
        'type' => 'custom',
        'is_automatic' => true,
        'is_active' => true,
        'conditions' => [['field' => 'status', 'value_status' => 'active']],
    ]);

    expect($activeOnly->countMatchingCustomers())->toBe(1);
});
