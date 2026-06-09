<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Customers\Actions\CreateCustomer;
use AIArmada\Customers\Actions\UpdateCustomerProfile;
use AIArmada\Customers\Models\Address;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerGroup;
use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Services\CustomerResolver;

describe('CustomerResolver', function (): void {
    it('does not resolve a non-guest customer by email for unauthenticated checkouts', function (): void {
        $resolver = new CustomerResolver(new CreateCustomer, new UpdateCustomerProfile);

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
        $resolver = new CustomerResolver(new CreateCustomer, new UpdateCustomerProfile);

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

    it('syncs purchaser company onto an existing guest customer resolved by email', function (): void {
        $resolver = new CustomerResolver(new CreateCustomer, new UpdateCustomerProfile);

        $guest = Customer::query()->create([
            'first_name' => 'Existing',
            'last_name' => 'Guest',
            'email' => 'existing-guest-' . uniqid() . '@example.com',
            'status' => 'active',
            'is_guest' => true,
            'company' => null,
            'user_id' => null,
        ]);

        $resolved = $resolver->resolve(
            user: null,
            sessionCustomer: null,
            billingData: [
                'email' => $guest->email,
                'name' => 'Existing Guest Example',
                'company' => 'Example Labs Sdn Bhd',
            ],
            shippingData: [],
        );

        expect($resolved)
            ->not->toBeNull()
            ->and($resolved?->id)->toBe($guest->id)
            ->and($resolved?->company)->toBe('Example Labs Sdn Bhd')
            ->and($resolved?->full_name)->toBe('Existing Guest Example');
    });

    it('merges segment and group memberships into the target customer', function (): void {
        $resolver = new CustomerResolver(new CreateCustomer, new UpdateCustomerProfile);

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

    it('preserves multi-word names and purchaser company when creating a guest customer', function (): void {
        $resolver = new CustomerResolver(new CreateCustomer, new UpdateCustomerProfile);

        $resolved = $resolver->resolve(
            user: null,
            sessionCustomer: null,
            billingData: [
                'email' => 'multi-name-' . uniqid() . '@example.com',
                'name' => 'Saiffil Checkout QA',
                'company' => 'Example Sdn Bhd',
            ],
            shippingData: [],
        );

        expect($resolved)
            ->not->toBeNull()
            ->and($resolved?->first_name)->toBe('Saiffil')
            ->and($resolved?->last_name)->toBe('Checkout QA')
            ->and($resolved?->company)->toBe('Example Sdn Bhd')
            ->and($resolved?->full_name)->toBe('Saiffil Checkout QA');
    });

    it('promotes an existing guest customer by email when the authenticated user has no customer yet', function (): void {
        $resolver = new CustomerResolver(new CreateCustomer, new UpdateCustomerProfile);

        $email = 'promote-existing-guest-' . uniqid() . '@example.com';

        $user = User::factory()->create([
            'email' => $email,
            'name' => 'Registered Guest Merge',
        ]);

        $guest = Customer::query()->create([
            'first_name' => 'Guest',
            'last_name' => 'Before Login',
            'email' => $email,
            'status' => 'active',
            'is_guest' => true,
            'user_id' => null,
        ]);

        $resolved = $resolver->resolve(
            user: $user,
            sessionCustomer: null,
            billingData: [
                'email' => $email,
                'line1' => '123 Upgrade Street',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
            shippingData: []
        );

        expect($resolved)
            ->not->toBeNull()
            ->and($resolved?->id)->toBe($guest->id)
            ->and((string) $resolved?->user_id)->toBe((string) $user->getKey())
            ->and($resolved?->is_guest)->toBeFalse()
            ->and($resolved?->addresses()->count())->toBe(1);
    });
});
