<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerNote;
use AIArmada\Customers\Policies\CustomerNotePolicy;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

require_once __DIR__ . '/Fixtures/CustomersTestOwner.php';

function bindNotePolicyOwnerResolver(?Model $owner): void
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
function createNotePolicyTestUser(array $permissions): Authorizable
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
function createNotePolicyCustomer(array $attributes, ?Model $owner = null): Customer
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
function createPolicyNote(array $attributes, ?Model $owner = null): CustomerNote
{
    /** @var CustomerNote $note */
    $note = OwnerContext::withOwner($owner, fn (): CustomerNote => CustomerNote::query()->create($attributes));

    return $note;
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

    $this->policy = new CustomerNotePolicy;
    $this->permitted = createNotePolicyTestUser([
        'customers.notes.view',
        'customers.notes.create',
        'customers.notes.update',
        'customers.notes.delete',
    ]);
    $this->denied = createNotePolicyTestUser([]);
});

describe('CustomerNotePolicy', function (): void {
    describe('viewAny', function (): void {
        it('allows viewing any notes with the view permission', function (): void {
            expect($this->policy->viewAny($this->permitted))->toBeTrue();
        });

        it('denies viewing any notes without the view permission', function (): void {
            expect($this->policy->viewAny($this->denied))->toBeFalse();
        });

        it('denies viewing any notes when unauthenticated', function (): void {
            expect($this->policy->viewAny(null))->toBeFalse();
        });
    });

    describe('view', function (): void {
        it('allows viewing global note without owner resolver', function (): void {
            $customer = createNotePolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            $note = createPolicyNote([
                'customer_id' => $customer->id,
                'content' => 'Test note content',
                'is_internal' => true,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->view($this->permitted, $note))->toBeTrue();
        });

        it('denies viewing owner-scoped note without owner resolver', function (): void {
            $owner = CustomersTestOwner::query()->create(['name' => 'Owner A']);

            $customer = createNotePolicyCustomer([
                'first_name' => 'Owned',
                'last_name' => 'Customer',
                'email' => 'owned-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
            ], $owner);

            $note = createPolicyNote([
                'customer_id' => $customer->id,
                'content' => 'Owned note content',
                'is_internal' => true,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
            ], $owner);

            expect($this->policy->view($this->permitted, $note))->toBeFalse();
        });

        it('denies cross-tenant note access when owner resolver is set', function (): void {
            $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
            $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

            $customerA = createNotePolicyCustomer([
                'first_name' => 'A',
                'last_name' => 'Customer',
                'email' => 'a-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
            ], $ownerA);

            $customerB = createNotePolicyCustomer([
                'first_name' => 'B',
                'last_name' => 'Customer',
                'email' => 'b-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => $ownerB->getKey(),
            ], $ownerB);

            $noteA = createPolicyNote([
                'customer_id' => $customerA->id,
                'content' => 'Note for A',
                'is_internal' => true,
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
            ], $ownerA);

            $noteB = createPolicyNote([
                'customer_id' => $customerB->id,
                'content' => 'Note for B',
                'is_internal' => true,
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => $ownerB->getKey(),
            ], $ownerB);

            bindNotePolicyOwnerResolver($ownerA);

            expect($this->policy->view($this->permitted, $noteA))->toBeTrue()
                ->and($this->policy->view($this->permitted, $noteB))->toBeFalse();
        });

        it('denies viewing an in-scope note without the view permission', function (): void {
            $customer = createNotePolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-noview-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            $note = createPolicyNote([
                'customer_id' => $customer->id,
                'content' => 'No view note content',
                'is_internal' => true,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->view($this->denied, $note))->toBeFalse();
        });
    });

    describe('create', function (): void {
        it('allows creating notes with the create permission', function (): void {
            expect($this->policy->create($this->permitted))->toBeTrue();
        });

        it('denies creating notes without the create permission', function (): void {
            expect($this->policy->create($this->denied))->toBeFalse();
        });

        it('denies creating notes when unauthenticated', function (): void {
            expect($this->policy->create(null))->toBeFalse();
        });
    });

    describe('update', function (): void {
        it('allows updating global note without owner resolver', function (): void {
            $customer = createNotePolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-update-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            $note = createPolicyNote([
                'customer_id' => $customer->id,
                'content' => 'Update note content',
                'is_internal' => false,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->update($this->permitted, $note))->toBeTrue();
        });

        it('denies updating an in-scope note without the update permission', function (): void {
            $customer = createNotePolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-noupdate-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            $note = createPolicyNote([
                'customer_id' => $customer->id,
                'content' => 'No update note content',
                'is_internal' => false,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->update($this->denied, $note))->toBeFalse();
        });

        it('denies updating note when unauthenticated', function (): void {
            $customer = createNotePolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-update-unauth-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            $note = createPolicyNote([
                'customer_id' => $customer->id,
                'content' => 'Update note content',
                'is_internal' => false,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->update(null, $note))->toBeFalse();
        });
    });

    describe('delete', function (): void {
        it('allows deleting global note without owner resolver', function (): void {
            $customer = createNotePolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-delete-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            $note = createPolicyNote([
                'customer_id' => $customer->id,
                'content' => 'Delete note content',
                'is_internal' => true,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->delete($this->permitted, $note))->toBeTrue();
        });

        it('denies deleting an in-scope note without the delete permission', function (): void {
            $customer = createNotePolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-nodelete-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            $note = createPolicyNote([
                'customer_id' => $customer->id,
                'content' => 'No delete note content',
                'is_internal' => true,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->delete($this->denied, $note))->toBeFalse();
        });

        it('denies deleting note when unauthenticated', function (): void {
            $customer = createNotePolicyCustomer([
                'first_name' => 'Global',
                'last_name' => 'Customer',
                'email' => 'global-delete-unauth-note-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            $note = createPolicyNote([
                'customer_id' => $customer->id,
                'content' => 'Delete note content',
                'is_internal' => true,
                'owner_type' => null,
                'owner_id' => null,
            ], null);

            expect($this->policy->delete(null, $note))->toBeFalse();
        });
    });
});
