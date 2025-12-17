<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Support\CustomersOwnerScope;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('filamentCustomers_makeOwner')) {
    function filamentCustomers_makeOwner(string $id): Model
    {
        return new class($id) extends Model
        {
            public $incrementing = false;

            protected $keyType = 'string';

            public function __construct(private readonly string $uuid) {}

            public function getKey(): mixed
            {
                return $this->uuid;
            }

            public function getMorphClass(): string
            {
                return 'tests:owner';
            }
        };
    }
}

it('resolveOwner returns null when resolver is not bound', function (): void {
    if (app()->bound(OwnerResolverInterface::class)) {
        app()->forgetInstance(OwnerResolverInterface::class);
        app()->offsetUnset(OwnerResolverInterface::class);
    }

    expect(app()->bound(OwnerResolverInterface::class))->toBeFalse();
    expect(CustomersOwnerScope::resolveOwner())->toBeNull();
});

it('applyToOwnedQuery returns global-only rows when no owner is resolved and model has no scopeForOwner', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    $global = Customer::query()->create([
        'first_name' => 'Global',
        'last_name' => 'Customer',
        'email' => 'global@example.com',
        'status' => 'active',
        'accepts_marketing' => false,
        'is_tax_exempt' => false,
        'wallet_balance' => 0,
        'lifetime_value' => 0,
        'total_orders' => 0,
        'owner_type' => null,
        'owner_id' => null,
    ]);

    Customer::query()->create([
        'first_name' => 'Owned',
        'last_name' => 'Customer',
        'email' => 'owned@example.com',
        'status' => 'active',
        'accepts_marketing' => false,
        'is_tax_exempt' => false,
        'wallet_balance' => 0,
        'lifetime_value' => 0,
        'total_orders' => 0,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $tableName = (new Customer)->getTable();

    $noScopeModel = new class extends Model {};
    $noScopeModel->setTable($tableName);

    $emails = CustomersOwnerScope::applyToOwnedQuery($noScopeModel->newQuery())
        ->orderBy('email')
        ->pluck('email')
        ->all();

    expect($emails)->toEqual([$global->email]);
});

it('applyToOwnedQuery returns only owner rows when owner is resolved and model has no scopeForOwner', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000b');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerA) implements OwnerResolverInterface {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    Customer::query()->create([
        'first_name' => 'Global',
        'last_name' => 'Customer',
        'email' => 'global2@example.com',
        'status' => 'active',
        'accepts_marketing' => false,
        'is_tax_exempt' => false,
        'wallet_balance' => 0,
        'lifetime_value' => 0,
        'total_orders' => 0,
        'owner_type' => null,
        'owner_id' => null,
    ]);

    Customer::query()->create([
        'first_name' => 'A',
        'last_name' => 'Customer',
        'email' => 'a@example.com',
        'status' => 'active',
        'accepts_marketing' => false,
        'is_tax_exempt' => false,
        'wallet_balance' => 0,
        'lifetime_value' => 0,
        'total_orders' => 0,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    Customer::query()->create([
        'first_name' => 'B',
        'last_name' => 'Customer',
        'email' => 'b@example.com',
        'status' => 'active',
        'accepts_marketing' => false,
        'is_tax_exempt' => false,
        'wallet_balance' => 0,
        'lifetime_value' => 0,
        'total_orders' => 0,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $tableName = (new Customer)->getTable();

    $noScopeModel = new class extends Model {};
    $noScopeModel->setTable($tableName);

    $emails = CustomersOwnerScope::applyToOwnedQuery($noScopeModel->newQuery())
        ->orderBy('email')
        ->pluck('email')
        ->all();

    expect($emails)->toEqual(['a@example.com']);
});
