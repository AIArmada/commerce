<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Policies\CustomerPolicy;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

require_once __DIR__ . '/Fixtures/CustomersTestOwner.php';

/**
 * @param  array<string>  $permissions
 */
function createPolicyIsolationUser(array $permissions): Authorizable
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
function createCustomerPolicyIsolationCustomer(array $attributes, ?Model $owner = null): Customer
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

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

it('does not allow viewing a customer outside owner scope even if user_id matches', function (): void {
    $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

    $user = User::query()->create([
        'name' => 'Viewer',
        'email' => 'viewer-' . uniqid() . '@example.com',
        'password' => 'password',
    ]);

    $customerOutsideOwner = createCustomerPolicyIsolationCustomer([
        'user_id' => $user->getKey(),
        'first_name' => 'Out',
        'last_name' => 'Scope',
        'email' => 'outside-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ], $ownerB);

    $policy = new CustomerPolicy;

    $viewer = createPolicyIsolationUser([
        'customers.customers.view',
        'customers.customers.update',
        'customers.customers.delete',
    ]);

    OwnerContext::withOwner($ownerA, function () use ($policy, $viewer, $customerOutsideOwner): void {
        expect($policy->view($viewer, $customerOutsideOwner))->toBeFalse();
        expect($policy->update($viewer, $customerOutsideOwner))->toBeFalse();
        expect($policy->delete($viewer, $customerOutsideOwner))->toBeFalse();
    });
});

it('allows a permissioned viewer to access a customer inside owner scope', function (): void {
    $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);

    $user = User::query()->create([
        'name' => 'Viewer',
        'email' => 'viewer-inside-' . uniqid() . '@example.com',
        'password' => 'password',
    ]);

    $customerInsideOwner = createCustomerPolicyIsolationCustomer([
        'user_id' => $user->getKey(),
        'first_name' => 'In',
        'last_name' => 'Scope',
        'email' => 'inside-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ], $ownerA);

    $policy = new CustomerPolicy;

    $viewer = createPolicyIsolationUser([
        'customers.customers.view',
        'customers.customers.update',
        'customers.customers.delete',
    ]);

    OwnerContext::withOwner($ownerA, function () use ($policy, $viewer, $customerInsideOwner): void {
        expect($policy->view($viewer, $customerInsideOwner))->toBeTrue();
        expect($policy->update($viewer, $customerInsideOwner))->toBeTrue();
        expect($policy->delete($viewer, $customerInsideOwner))->toBeTrue();
    });
});
