<?php

declare(strict_types=1);

use AIArmada\Customers\Models\Address;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerGroup;
use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Services\CustomerResolver;

describe('CustomerResolver', function (): void {
    it('does not resolve a non-guest customer by email for unauthenticated checkouts', function (): void {
        $resolver = new CustomerResolver;

        $existing = Customer::query()->create([
            'first_name' => 'Registered',
            'last_name' => 'Customer',
            'email' => 'registered-' . uniqid() . '@example.com',
            'status' => 'active',
            'is_guest' => false,
            'user_id' => (string) fake()->uuid(),
        ]);

        $resolved = $resolver->resolve(
            user: null,
            sessionCustomer: null,
            billingData: [
                'email' => $existing->email,
                'line1' => '123 Changed Street',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
            shippingData: []
        );

        expect($resolved)->toBeNull();

        expect(Address::query()->count())->toBe(0);

        expect($existing->fresh())
            ->not->toBeNull()
            ->and($existing->fresh()?->is_guest)->toBeFalse();
    });

    it('resolves existing guest customer by email for unauthenticated checkouts', function (): void {
        $resolver = new CustomerResolver;

        $guest = Customer::query()->create([
            'first_name' => 'Guest',
            'last_name' => 'Customer',
            'email' => 'guest-' . uniqid() . '@example.com',
            'status' => 'active',
            'is_guest' => true,
            'user_id' => null,
        ]);

        $resolved = $resolver->resolve(
            user: null,
            sessionCustomer: null,
            billingData: [
                'email' => $guest->email,
                'line1' => '123 Guest Street',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
            shippingData: []
        );

        expect($resolved)
            ->not->toBeNull()
            ->and($resolved?->id)->toBe($guest->id);

        expect($guest->fresh()?->addresses()->count())->toBe(1);
    });

    it('merges segment and group memberships into the target customer', function (): void {
        $resolver = new CustomerResolver;

        $source = Customer::query()->create([
            'first_name' => 'Source',
            'last_name' => 'Guest',
            'email' => 'source-' . uniqid() . '@example.com',
            'status' => 'active',
            'is_guest' => true,
        ]);

        $target = Customer::query()->create([
            'first_name' => 'Target',
            'last_name' => 'User',
            'email' => 'target-' . uniqid() . '@example.com',
            'status' => 'active',
            'is_guest' => false,
        ]);

        $segment = Segment::query()->create([
            'name' => 'Merge Segment ' . uniqid(),
            'slug' => 'merge-segment-' . uniqid(),
            'is_automatic' => false,
            'is_active' => true,
        ]);

        $group = CustomerGroup::query()->create([
            'name' => 'Merge Group ' . uniqid(),
            'is_active' => true,
        ]);

        $source->segments()->attach($segment->id);
        $source->groups()->attach($group->id, ['role' => 'member', 'joined_at' => now()]);

        $merged = $resolver->mergeCustomers($source, $target);

        expect($merged->id)->toBe($target->id);
        expect($merged->segments()->whereKey($segment->id)->exists())->toBeTrue();
        expect($merged->groups()->whereKey($group->id)->exists())->toBeTrue();
        expect(Customer::query()->whereKey($source->id)->exists())->toBeFalse();
    });
});
