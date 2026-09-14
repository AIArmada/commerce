<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Policies\CustomerPolicy;
use AIArmada\Customers\Policies\SegmentPolicy;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

require_once __DIR__ . '/Fixtures/CustomersTestOwner.php';

function bindCustomersOwnerResolver(?Model $owner): void
{
    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

/**
 * @param  array<string>  $permissions
 */
function createCustomerPoliciesTestUser(array $permissions): Authorizable
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
function createPolicyCustomer(array $attributes, ?Model $owner = null): Customer
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
function createPolicySegment(array $attributes, ?Model $owner = null): Segment
{
    /** @var Segment $segment */
    $segment = OwnerContext::withOwner($owner, fn (): Segment => Segment::query()->create($attributes));

    return $segment;
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
});

describe('CustomerPolicy', function (): void {
    beforeEach(function (): void {
        $this->policy = new CustomerPolicy;
        $this->permitted = createCustomerPoliciesTestUser([
            'customers.customers.view',
            'customers.customers.create',
            'customers.customers.update',
            'customers.customers.delete',
            'customers.customers.add-credit',
            'customers.customers.deduct-credit',
        ]);
        $this->denied = createCustomerPoliciesTestUser([]);
    });

    describe('viewAny', function (): void {
        it('allows viewing any customers with the view permission', function (): void {
            expect($this->policy->viewAny($this->permitted))->toBeTrue();
        });

        it('denies viewing any customers without the view permission', function (): void {
            expect($this->policy->viewAny($this->denied))->toBeFalse();
        });

        it('denies viewing any customers when unauthenticated', function (): void {
            expect($this->policy->viewAny(null))->toBeFalse();
        });
    });

    describe('view', function (): void {
        it('allows viewing global customer without owner resolver', function (): void {
            $globalCustomer = createPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
                'user_id' => null,
            ], null);

            expect($this->policy->view($this->permitted, $globalCustomer))->toBeTrue();
        });

        it('denies viewing owner-scoped customer without owner resolver', function (): void {
            $owner = CustomersTestOwner::query()->create(['name' => 'Owner A']);

            $customer = createPolicyCustomer([
                'first_name' => 'Owned',
                'last_name' => 'Customer',
                'email' => 'owned-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'user_id' => null,
            ], $owner);

            expect($this->policy->view($this->permitted, $customer))->toBeFalse();
        });

        it('denies cross-tenant customer access when owner resolver is set', function (): void {
            $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
            $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

            $customerA = createPolicyCustomer([
                'first_name' => 'A',
                'last_name' => 'Customer',
                'email' => 'a-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
                'user_id' => null,
            ], $ownerA);

            $customerB = createPolicyCustomer([
                'first_name' => 'B',
                'last_name' => 'Customer',
                'email' => 'b-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => $ownerB->getKey(),
                'user_id' => null,
            ], $ownerB);

            bindCustomersOwnerResolver($ownerA);

            expect($this->policy->view($this->permitted, $customerA))->toBeTrue()
                ->and($this->policy->update($this->permitted, $customerA))->toBeTrue()
                ->and($this->policy->view($this->permitted, $customerB))->toBeFalse()
                ->and($this->policy->update($this->permitted, $customerB))->toBeFalse();
        });

        it('denies viewing an in-scope customer without the view permission', function (): void {
            $globalCustomer = createPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-noview-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
                'user_id' => null,
            ], null);

            expect($this->policy->view($this->denied, $globalCustomer))->toBeFalse();
        });
    });

    describe('create', function (): void {
        it('allows creating customers with the create permission', function (): void {
            expect($this->policy->create($this->permitted))->toBeTrue();
        });

        it('denies creating customers without the create permission', function (): void {
            expect($this->policy->create($this->denied))->toBeFalse();
        });

        it('denies creating customers when unauthenticated', function (): void {
            expect($this->policy->create(null))->toBeFalse();
        });
    });

    describe('update', function (): void {
        it('allows updating global customer without owner resolver', function (): void {
            $globalCustomer = createPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-update-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->update($this->permitted, $globalCustomer))->toBeTrue();
        });

        it('denies updating an in-scope customer without the update permission', function (): void {
            $globalCustomer = createPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-noupdate-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->update($this->denied, $globalCustomer))->toBeFalse();
        });
    });

    describe('delete', function (): void {
        it('allows deleting global customer without owner resolver', function (): void {
            $globalCustomer = createPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-delete-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->delete($this->permitted, $globalCustomer))->toBeTrue();
        });

        it('denies deleting an in-scope customer without the delete permission', function (): void {
            $globalCustomer = createPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-nodelete-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->delete($this->denied, $globalCustomer))->toBeFalse();
        });
    });

    describe('addCredit', function (): void {
        it('allows adding credit', function (): void {
            $globalCustomer = createPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-credit-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->addCredit($this->permitted, $globalCustomer))->toBeTrue();
        });

        it('denies adding credit without the add-credit permission', function (): void {
            $globalCustomer = createPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-credit-denied-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->addCredit($this->denied, $globalCustomer))->toBeFalse();
        });

        it('denies adding credit when unauthenticated', function (): void {
            $customer = createPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-credit-unauth-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->addCredit(null, $customer))->toBeFalse();
        });
    });

    describe('deductCredit', function (): void {
        it('allows deducting credit', function (): void {
            $globalCustomer = createPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-debit-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->deductCredit($this->permitted, $globalCustomer))->toBeTrue();
        });

        it('denies deducting credit without the deduct-credit permission', function (): void {
            $globalCustomer = createPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-debit-denied-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->deductCredit($this->denied, $globalCustomer))->toBeFalse();
        });

        it('denies deducting credit when unauthenticated', function (): void {
            $customer = createPolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-debit-unauth-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->deductCredit(null, $customer))->toBeFalse();
        });
    });
});

describe('SegmentPolicy', function (): void {
    beforeEach(function (): void {
        $this->policy = new SegmentPolicy;
        $this->permitted = createCustomerPoliciesTestUser([
            'customers.segments.view',
            'customers.segments.create',
            'customers.segments.update',
            'customers.segments.delete',
            'customers.segments.rebuild',
        ]);
        $this->denied = createCustomerPoliciesTestUser([]);
    });

    describe('viewAny', function (): void {
        it('allows viewing any segments with the view permission', function (): void {
            expect($this->policy->viewAny($this->permitted))->toBeTrue();
        });

        it('denies viewing any segments without the view permission', function (): void {
            expect($this->policy->viewAny($this->denied))->toBeFalse();
        });

        it('denies viewing any segments when unauthenticated', function (): void {
            expect($this->policy->viewAny(null))->toBeFalse();
        });
    });

    describe('view', function (): void {
        it('allows viewing global segments without owner resolver', function (): void {
            $segment = createPolicySegment([
                'name' => 'Global Segment',
                'slug' => 'global-segment-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->view($this->permitted, $segment))->toBeTrue();
        });

        it('denies cross-tenant segment access when owner resolver is set', function (): void {
            $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
            $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

            $segmentA = createPolicySegment([
                'name' => 'A Segment',
                'slug' => 'a-segment-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
            ], $ownerA);

            $segmentB = createPolicySegment([
                'name' => 'B Segment',
                'slug' => 'b-segment-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => $ownerB->getKey(),
            ], $ownerB);

            bindCustomersOwnerResolver($ownerA);

            expect($this->policy->view($this->permitted, $segmentA))->toBeTrue()
                ->and($this->policy->update($this->permitted, $segmentA))->toBeTrue()
                ->and($this->policy->view($this->permitted, $segmentB))->toBeFalse()
                ->and($this->policy->update($this->permitted, $segmentB))->toBeFalse();
        });

        it('denies viewing an in-scope segment without the view permission', function (): void {
            $segment = createPolicySegment([
                'name' => 'Global No View Segment',
                'slug' => 'global-no-view-segment-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->view($this->denied, $segment))->toBeFalse();
        });
    });

    describe('create', function (): void {
        it('allows creating segments with the create permission', function (): void {
            expect($this->policy->create($this->permitted))->toBeTrue();
        });

        it('denies creating segments without the create permission', function (): void {
            expect($this->policy->create($this->denied))->toBeFalse();
        });

        it('denies creating segments when unauthenticated', function (): void {
            expect($this->policy->create(null))->toBeFalse();
        });
    });

    describe('update', function (): void {
        it('allows updating global segments without owner resolver', function (): void {
            $segment = createPolicySegment([
                'name' => 'Global Update Segment',
                'slug' => 'global-update-segment-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->update($this->permitted, $segment))->toBeTrue();
        });

        it('denies updating an in-scope segment without the update permission', function (): void {
            $segment = createPolicySegment([
                'name' => 'Global No Update Segment',
                'slug' => 'global-no-update-segment-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->update($this->denied, $segment))->toBeFalse();
        });
    });

    describe('delete', function (): void {
        it('allows deleting global segments without owner resolver', function (): void {
            $segment = createPolicySegment([
                'name' => 'Global Delete Segment',
                'slug' => 'global-delete-segment-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->delete($this->permitted, $segment))->toBeTrue();
        });

        it('denies deleting an in-scope segment without the delete permission', function (): void {
            $segment = createPolicySegment([
                'name' => 'Global No Delete Segment',
                'slug' => 'global-no-delete-segment-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->delete($this->denied, $segment))->toBeFalse();
        });
    });

    describe('rebuild', function (): void {
        it('allows rebuilding automatic segments', function (): void {
            $segment = createPolicySegment([
                'name' => 'Rebuild Segment',
                'slug' => 'rebuild-segment-' . uniqid(),
                'is_automatic' => true,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->rebuild($this->permitted, $segment))->toBeTrue();
        });

        it('denies rebuilding without the rebuild permission', function (): void {
            $segment = createPolicySegment([
                'name' => 'No Rebuild Segment',
                'slug' => 'no-rebuild-segment-' . uniqid(),
                'is_automatic' => true,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            $updater = createCustomerPoliciesTestUser(['customers.segments.update']);

            expect($this->policy->rebuild($updater, $segment))->toBeFalse()
                ->and($this->policy->rebuild($this->denied, $segment))->toBeFalse();
        });

        it('denies rebuilding manual segments', function (): void {
            $manualSegment = createPolicySegment([
                'name' => 'Manual',
                'slug' => 'manual-' . uniqid(),
                'is_automatic' => false,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->rebuild($this->permitted, $manualSegment))->toBeFalse();
        });
    });
});
