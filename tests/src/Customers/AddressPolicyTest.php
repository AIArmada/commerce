<?php

declare(strict_types=1);

use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Policies\AddressPolicy;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

require_once __DIR__ . '/Fixtures/CustomersTestOwner.php';

function bindAddressPolicyOwnerResolver(?Model $owner): void
{
    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?RecordReference
        {
            return EloquentRecordReference::fromModel($this->owner);
        }
    });
}

/**
 * @param  array<string>  $permissions
 */
function createAddressPolicyTestUser(array $permissions): Authorizable
{
    return new class($permissions) implements Authorizable
    {
        /**
         * @param  array<string>  $permissions
         */
        public function __construct(private readonly array $permissions) {}

        public function can($abilities, $arguments = []): bool
        {
            $abilities = is_array($abilities) ? $abilities : [$abilities];

            return array_intersect($abilities, $this->permissions) !== [];
        }
    };
}

/**
 * @param  array<string, mixed>  $attributes
 */
function createAddressPolicyCustomer(array $attributes, ?Model $owner = null): Customer
{
    $restricted = [];

    foreach (['user_id', 'status', 'is_guest', 'accepts_marketing', 'created_at', 'updated_at'] as $key) {
        if (array_key_exists($key, $attributes)) {
            $restricted[$key] = $attributes[$key];
            unset($attributes[$key]);
        }
    }

    return OwnerContext::withOwner($owner, function () use ($attributes, $restricted): Customer {
        /** @var Customer $customer */
        $customer = Customer::query()->create($attributes);

        if ($restricted !== []) {
            $customer->forceFill($restricted)->save();
        }

        return $customer;
    });
}

/**
 * @param  array<string, mixed>  $attributes
 */
function createAddressPolicyAddress(array $attributes, ?Model $owner = null): Address
{
    /** @var Address $address */
    $address = OwnerContext::withOwner($owner, fn (): Address => Address::query()->create($attributes));

    return $address;
}

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    if (app()->bound(OwnerResolverInterface::class)) {
        app()->forgetInstance(OwnerResolverInterface::class);
        app()->offsetUnset(OwnerResolverInterface::class);
    }

    $this->policy = new AddressPolicy;
    $this->permitted = createAddressPolicyTestUser([
        'customers.addresses.view',
        'customers.addresses.create',
        'customers.addresses.update',
        'customers.addresses.delete',
    ]);
    $this->denied = createAddressPolicyTestUser([]);
});

describe('AddressPolicy', function (): void {
    describe('viewAny', function (): void {
        it('allows viewing any addresses with the view permission', function (): void {
            expect($this->policy->viewAny($this->permitted))->toBeTrue();
        });

        it('denies viewing any addresses without the view permission', function (): void {
            expect($this->policy->viewAny($this->denied))->toBeFalse();
        });

        it('denies viewing any addresses when unauthenticated', function (): void {
            expect($this->policy->viewAny(null))->toBeFalse();
        });
    });

    describe('view', function (): void {
        it('denies viewing global address without owner context', function (): void {
            $customer = createAddressPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            $address = createAddressPolicyAddress([
                'line1' => '123 Test St',
                'city' => 'Test City',
                'postcode' => '12345',
                'country' => 'MY',
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->view($this->permitted, $address))->toBeFalse()
                ->and(OwnerContext::withOwner(null, fn (): bool => $this->policy->view($this->permitted, $address)))->toBeTrue();
        });

        it('allows configured global reads only when include_global is enabled', function (): void {
            $owner = CustomersTestOwner::query()->create(['name' => 'Owner A']);
            $address = createAddressPolicyAddress([
                'line1' => '123 Test St',
                'city' => 'Test City',
                'postcode' => '12345',
                'country' => 'MY',
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            bindAddressPolicyOwnerResolver($owner);

            expect($this->policy->view($this->permitted, $address))->toBeFalse();

            config()->set('addressing.features.owner.include_global', true);

            expect($this->policy->view($this->permitted, $address))->toBeTrue()
                ->and($this->policy->update($this->permitted, $address))->toBeFalse();
        });

        it('denies viewing owner-scoped address without owner resolver', function (): void {
            $owner = CustomersTestOwner::query()->create(['name' => 'Owner A']);

            $customer = createAddressPolicyCustomer([
                'first_name' => 'Owned',
                'last_name' => 'Customer',
                'email' => 'owned-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
            ], $owner);

            $address = createAddressPolicyAddress([
                'line1' => '123 Test St',
                'city' => 'Test City',
                'postcode' => '12345',
                'country' => 'MY',
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
            ], $owner);

            expect($this->policy->view($this->permitted, $address))->toBeFalse();
        });

        it('denies cross-tenant address access when owner resolver is set', function (): void {
            $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
            $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

            $customerA = createAddressPolicyCustomer([
                'first_name' => 'A',
                'last_name' => 'Customer',
                'email' => 'a-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
            ], $ownerA);

            $customerB = createAddressPolicyCustomer([
                'first_name' => 'B',
                'last_name' => 'Customer',
                'email' => 'b-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => $ownerB->getKey(),
            ], $ownerB);

            $addressA = createAddressPolicyAddress([
                'line1' => '123 A St',
                'city' => 'A City',
                'postcode' => '11111',
                'country' => 'MY',
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
            ], $ownerA);

            $addressB = createAddressPolicyAddress([
                'line1' => '456 B St',
                'city' => 'B City',
                'postcode' => '22222',
                'country' => 'MY',
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => $ownerB->getKey(),
            ], $ownerB);

            bindAddressPolicyOwnerResolver($ownerA);

            expect($this->policy->view($this->permitted, $addressA))->toBeTrue()
                ->and($this->policy->view($this->permitted, $addressB))->toBeFalse();
        });

        it('denies viewing an in-scope address without the view permission', function (): void {
            $address = createAddressPolicyAddress([
                'line1' => '123 Test St',
                'city' => 'Test City',
                'postcode' => '12345',
                'country' => 'MY',
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->view($this->denied, $address))->toBeFalse();
        });
    });

    describe('create', function (): void {
        it('allows creating addresses with the create permission', function (): void {
            expect($this->policy->create($this->permitted))->toBeTrue();
        });

        it('denies creating addresses without the create permission', function (): void {
            expect($this->policy->create($this->denied))->toBeFalse();
        });

        it('denies creating addresses when unauthenticated', function (): void {
            expect($this->policy->create(null))->toBeFalse();
        });
    });

    describe('update', function (): void {
        it('requires explicit global context to update a global address', function (): void {
            $customer = createAddressPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-update-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            $address = createAddressPolicyAddress([
                'line1' => '789 Update St',
                'city' => 'Update City',
                'postcode' => '33333',
                'country' => 'MY',
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->update($this->permitted, $address))->toBeFalse()
                ->and(OwnerContext::withOwner(null, fn (): bool => $this->policy->update($this->permitted, $address)))->toBeTrue();
        });

        it('denies updating an in-scope address without the update permission', function (): void {
            $address = createAddressPolicyAddress([
                'line1' => '789 Update St',
                'city' => 'Update City',
                'postcode' => '33333',
                'country' => 'MY',
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->update($this->denied, $address))->toBeFalse();
        });

        it('denies updating address when unauthenticated', function (): void {
            $customer = createAddressPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-update-unauth-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            $address = createAddressPolicyAddress([
                'line1' => '789 Update St',
                'city' => 'Update City',
                'postcode' => '33333',
                'country' => 'MY',
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->update(null, $address))->toBeFalse();
        });
    });

    describe('delete', function (): void {
        it('requires explicit global context to delete a global address', function (): void {
            $customer = createAddressPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-delete-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            $address = createAddressPolicyAddress([
                'line1' => '999 Delete St',
                'city' => 'Delete City',
                'postcode' => '44444',
                'country' => 'MY',
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->delete($this->permitted, $address))->toBeFalse()
                ->and(OwnerContext::withOwner(null, fn (): bool => $this->policy->delete($this->permitted, $address)))->toBeTrue();
        });

        it('denies deleting an in-scope address without the delete permission', function (): void {
            $address = createAddressPolicyAddress([
                'line1' => '999 Delete St',
                'city' => 'Delete City',
                'postcode' => '44444',
                'country' => 'MY',
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->delete($this->denied, $address))->toBeFalse();
        });

        it('denies deleting address when unauthenticated', function (): void {
            $customer = createAddressPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-delete-unauth-addr-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            $address = createAddressPolicyAddress([
                'line1' => '999 Delete St',
                'city' => 'Delete City',
                'postcode' => '44444',
                'country' => 'MY',
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->delete(null, $address))->toBeFalse();
        });
    });
});
