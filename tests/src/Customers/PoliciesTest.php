<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Policies\CustomerPolicy;
use AIArmada\Customers\Policies\SegmentPolicy;
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
        $this->user = new class
        {
            public string $id = 'user-a';
        };
    });

    describe('viewAny', function (): void {
        it('allows viewing any customers', function (): void {
            expect($this->policy->viewAny($this->user))->toBeTrue();
        });

        it('denies viewing any customers when unauthenticated', function (): void {
            expect($this->policy->viewAny(null))->toBeFalse();
        });
    });

    describe('view', function (): void {
        it('allows viewing global customer without owner resolver', function (): void {
            $globalCustomer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
                'user_id' => null,
            ]);

            expect($this->policy->view($this->user, $globalCustomer))->toBeTrue();
        });

        it('denies viewing owner-scoped customer without owner resolver', function (): void {
            $owner = CustomersTestOwner::query()->create(['name' => 'Owner A']);

            $customer = Customer::query()->create([
                'first_name' => 'Owned',
                'last_name' => 'Customer',
                'email' => 'owned-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'user_id' => null,
            ]);

            expect($this->policy->view($this->user, $customer))->toBeFalse();
        });

        it('denies cross-tenant customer access when owner resolver is set', function (): void {
            $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
            $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

            $customerA = Customer::query()->create([
                'first_name' => 'A',
                'last_name' => 'Customer',
                'email' => 'a-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
                'user_id' => null,
            ]);

            $customerB = Customer::query()->create([
                'first_name' => 'B',
                'last_name' => 'Customer',
                'email' => 'b-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => $ownerB->getKey(),
                'user_id' => null,
            ]);

            bindCustomersOwnerResolver($ownerA);

            expect($this->policy->view($this->user, $customerA))->toBeTrue()
                ->and($this->policy->update($this->user, $customerA))->toBeTrue()
                ->and($this->policy->view($this->user, $customerB))->toBeFalse()
                ->and($this->policy->update($this->user, $customerB))->toBeFalse();
        });
    });

    describe('create', function (): void {
        it('allows creating customers', function (): void {
            expect($this->policy->create($this->user))->toBeTrue();
        });

        it('denies creating customers when unauthenticated', function (): void {
            expect($this->policy->create(null))->toBeFalse();
        });
    });

    describe('update', function (): void {
        it('allows updating global customer without owner resolver', function (): void {
            $globalCustomer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-update-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->update($this->user, $globalCustomer))->toBeTrue();
        });
    });

    describe('delete', function (): void {
        it('allows deleting global customer without owner resolver', function (): void {
            $globalCustomer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-delete-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->delete($this->user, $globalCustomer))->toBeTrue();
        });
    });

    describe('addCredit', function (): void {
        it('allows adding credit', function (): void {
            $globalCustomer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-credit-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->addCredit($this->user, $globalCustomer))->toBeTrue();
        });

        it('denies adding credit when unauthenticated', function (): void {
            $customer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-credit-unauth-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->addCredit(null, $customer))->toBeFalse();
        });
    });

    describe('deductCredit', function (): void {
        it('allows deducting credit', function (): void {
            $globalCustomer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-debit-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->deductCredit($this->user, $globalCustomer))->toBeTrue();
        });

        it('denies deducting credit when unauthenticated', function (): void {
            $customer = Customer::query()->create([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-debit-unauth-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->deductCredit(null, $customer))->toBeFalse();
        });
    });
});

describe('SegmentPolicy', function (): void {
    beforeEach(function (): void {
        $this->policy = new SegmentPolicy;
        $this->user = new class
        {
            public string $id = 'user-a';
        };
    });

    describe('viewAny', function (): void {
        it('allows viewing any segments', function (): void {
            expect($this->policy->viewAny($this->user))->toBeTrue();
        });

        it('denies viewing any segments when unauthenticated', function (): void {
            expect($this->policy->viewAny(null))->toBeFalse();
        });
    });

    describe('view', function (): void {
        it('allows viewing global segments without owner resolver', function (): void {
            $segment = Segment::query()->create([
                'name' => 'Global Segment',
                'slug' => 'global-segment-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->view($this->user, $segment))->toBeTrue();
        });

        it('denies cross-tenant segment access when owner resolver is set', function (): void {
            $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
            $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

            $segmentA = Segment::query()->create([
                'name' => 'A Segment',
                'slug' => 'a-segment-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
            ]);

            $segmentB = Segment::query()->create([
                'name' => 'B Segment',
                'slug' => 'b-segment-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => $ownerB->getKey(),
            ]);

            bindCustomersOwnerResolver($ownerA);

            expect($this->policy->view($this->user, $segmentA))->toBeTrue()
                ->and($this->policy->update($this->user, $segmentA))->toBeTrue()
                ->and($this->policy->view($this->user, $segmentB))->toBeFalse()
                ->and($this->policy->update($this->user, $segmentB))->toBeFalse();
        });
    });

    describe('create', function (): void {
        it('allows creating segments', function (): void {
            expect($this->policy->create($this->user))->toBeTrue();
        });

        it('denies creating segments when unauthenticated', function (): void {
            expect($this->policy->create(null))->toBeFalse();
        });
    });

    describe('update', function (): void {
        it('allows updating global segments without owner resolver', function (): void {
            $segment = Segment::query()->create([
                'name' => 'Global Update Segment',
                'slug' => 'global-update-segment-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->update($this->user, $segment))->toBeTrue();
        });
    });

    describe('delete', function (): void {
        it('allows deleting global segments without owner resolver', function (): void {
            $segment = Segment::query()->create([
                'name' => 'Global Delete Segment',
                'slug' => 'global-delete-segment-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->delete($this->user, $segment))->toBeTrue();
        });
    });

    describe('rebuild', function (): void {
        it('allows rebuilding automatic segments', function (): void {
            $segment = Segment::query()->create([
                'name' => 'Rebuild Segment',
                'slug' => 'rebuild-segment-' . uniqid(),
                'is_automatic' => true,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->rebuild($this->user, $segment))->toBeTrue();
        });

        it('denies rebuilding manual segments', function (): void {
            $manualSegment = Segment::create([
                'name' => 'Manual',
                'slug' => 'manual-' . uniqid(),
                'is_automatic' => false,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            expect($this->policy->rebuild($this->user, $manualSegment))->toBeFalse();
        });
    });
});
