<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Customers\Actions\CreateCustomer;
use AIArmada\Customers\Actions\UpdateCustomerProfile;
use AIArmada\Customers\Models\Address;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerGroup;
use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Services\CustomerResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

require_once __DIR__ . '/Fixtures/CustomersTestOwner.php';

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

describe('CustomerResolver', function (): void {
    it('does not resolve a non-guest customer by email for unauthenticated checkouts', function (): void {
        $resolver = new CustomerResolver(new CreateCustomer, new UpdateCustomerProfile);

        $email = 'registered-' . uniqid() . '@example.com';

        $existing = Customer::query()->create([
            'first_name' => 'Registered',
            'last_name' => 'Customer',
            'email' => $email,
            'status' => 'active',
            'is_guest' => false,
            'user_id' => (string) fake()->uuid(),
        ]);
        $existing->addContactMethod(ContactMethodData::email($email));

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

        $owner = CustomersTestOwner::query()->create(['name' => 'Resolver Owner']);
        $email = 'guest-' . uniqid() . '@example.com';

        [$guest, $resolved] = OwnerContext::withOwner($owner, function () use ($email, $resolver): array {
            $guest = Customer::query()->create([
                'first_name' => 'Guest',
                'last_name' => 'Customer',
                'email' => $email,
                'status' => 'active',
                'is_guest' => true,
                'user_id' => null,
            ]);
            $guest->addContactMethod(ContactMethodData::email($email));

            $resolved = $resolver->resolve(
                user: null,
                sessionCustomer: null,
                billingData: [
                    'email' => $email,
                    'line1' => '123 Guest Street',
                    'city' => 'Kuala Lumpur',
                    'postcode' => '50000',
                    'country' => 'MY',
                ],
                shippingData: []
            );

            expect($guest->fresh()?->addresses()->count())->toBe(1);

            return [$guest, $resolved];
        });

        expect($resolved)
            ->not->toBeNull()
            ->and($resolved?->id)->toBe($guest->id);
    });

    it('resolves existing guest customer by raw email column even without contact methods', function (): void {
        $resolver = new CustomerResolver(new CreateCustomer, new UpdateCustomerProfile);

        $owner = CustomersTestOwner::query()->create(['name' => 'Resolver Owner']);
        $email = 'legacy-guest-' . uniqid() . '@example.com';

        [$guest, $resolved] = OwnerContext::withOwner($owner, function () use ($email, $resolver): array {
            $guest = Customer::query()->create([
                'first_name' => 'Legacy',
                'last_name' => 'Guest',
                'email' => $email,
                'status' => 'active',
                'is_guest' => true,
                'user_id' => null,
            ]);

            $resolved = $resolver->resolve(
                user: null,
                sessionCustomer: null,
                billingData: [
                    'email' => $email,
                    'line1' => '123 Legacy Street',
                    'city' => 'Kuala Lumpur',
                    'postcode' => '50000',
                    'country' => 'MY',
                ],
                shippingData: [],
            );

            return [$guest, $resolved];
        });

        expect($resolved)
            ->not->toBeNull()
            ->and($resolved?->id)->toBe($guest->id);
    });

    it('resolves existing guest customer by email contact method even without normalized value', function (): void {
        $resolver = new CustomerResolver(new CreateCustomer, new UpdateCustomerProfile);

        $owner = CustomersTestOwner::query()->create(['name' => 'Resolver Owner']);
        $email = 'legacy-contact-' . uniqid() . '@example.com';

        [$guest, $resolved] = OwnerContext::withOwner($owner, function () use ($email, $resolver): array {
            $guest = Customer::query()->create([
                'first_name' => 'Legacy',
                'last_name' => 'Contact',
                'email' => 'different-' . uniqid() . '@example.com',
                'status' => 'active',
                'is_guest' => true,
                'user_id' => null,
            ]);
            $contactMethod = $guest->addContactMethod(ContactMethodData::email($email));

            ContactMethod::query()
                ->whereKey($contactMethod->id)
                ->update(['normalized_value' => null]);

            $resolved = $resolver->resolve(
                user: null,
                sessionCustomer: null,
                billingData: [
                    'email' => $email,
                    'line1' => '123 Legacy Street',
                    'city' => 'Kuala Lumpur',
                    'postcode' => '50000',
                    'country' => 'MY',
                ],
                shippingData: [],
            );

            return [$guest, $resolved];
        });

        expect($resolved)
            ->not->toBeNull()
            ->and($resolved?->id)->toBe($guest->id);
    });

    it('syncs purchaser company onto an existing guest customer resolved by email', function (): void {
        $resolver = new CustomerResolver(new CreateCustomer, new UpdateCustomerProfile);

        $owner = CustomersTestOwner::query()->create(['name' => 'Resolver Owner']);

        [$guest, $resolved] = OwnerContext::withOwner($owner, function () use ($resolver): array {
            $email = 'existing-guest-' . uniqid() . '@example.com';

            $guest = Customer::query()->create([
                'first_name' => 'Existing',
                'last_name' => 'Guest',
                'email' => $email,
                'status' => 'active',
                'is_guest' => true,
                'company' => null,
                'user_id' => null,
            ]);
            $guest->addContactMethod(ContactMethodData::email($email));

            $resolved = $resolver->resolve(
                user: null,
                sessionCustomer: null,
                billingData: [
                    'email' => $email,
                    'name' => 'Existing Guest Example',
                    'company' => 'Example Labs Sdn Bhd',
                ],
                shippingData: [],
            );

            return [$guest, $resolved];
        });

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

        $owner = CustomersTestOwner::query()->create(['name' => 'Resolver Owner']);

        [$guest, $resolved] = OwnerContext::withOwner($owner, function () use ($email, $resolver, $user): array {
            $guest = Customer::query()->create([
                'first_name' => 'Guest',
                'last_name' => 'Before Login',
                'email' => $email,
                'status' => 'active',
                'is_guest' => true,
                'user_id' => null,
            ]);
            $guest->addContactMethod(ContactMethodData::email($email));

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

            expect($guest->fresh()?->addresses()->count())->toBe(1);

            return [$guest, $resolved];
        });

        expect($resolved)
            ->not->toBeNull()
            ->and($resolved?->id)->toBe($guest->id)
            ->and((string) $resolved?->user_id)->toBe((string) $user->getKey())
            ->and($resolved?->is_guest)->toBeFalse();
    });
});
