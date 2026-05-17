<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

beforeEach(function (): void {
    config()->set('customers.features.owner.enabled', true);
});

it('resolveOwner fails closed when resolver is not bound and context is not explicit global', function (): void {
    if (app()->bound(OwnerResolverInterface::class)) {
        app()->forgetInstance(OwnerResolverInterface::class);
        app()->offsetUnset(OwnerResolverInterface::class);
    }

    expect(app()->bound(OwnerResolverInterface::class))->toBeFalse();
    expect(fn (): ?Model => OwnerUiScope::resolveOwner(Customer::class))->toThrow(NoCurrentOwnerException::class);
});

it('apply returns global-only rows in explicit global context when model has no scopeForOwner', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    $global = OwnerContext::withOwner(null, fn (): Customer => Customer::query()->create([
        'first_name' => 'Global',
        'last_name' => 'Customer',
        'email' => 'global@example.com',
        'status' => 'active',
        'accepts_marketing' => false,

        'owner_type' => null,
        'owner_id' => null,
    ]));

    OwnerContext::withOwner($ownerA, fn (): Customer => Customer::query()->create([
        'first_name' => 'Owned',
        'last_name' => 'Customer',
        'email' => 'owned@example.com',
        'status' => 'active',
        'accepts_marketing' => false,

        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]));

    $tableName = (new Customer)->getTable();

    $noScopeModel = new class extends Model {};
    $noScopeModel->setTable($tableName);

    $emails = OwnerContext::withOwner(null, fn (): array => OwnerUiScope::apply($noScopeModel->newQuery(), configKey: 'customers.features.owner', includeGlobal: false)
        ->orderBy('email')
        ->pluck('email')
        ->all());

    expect($emails)->toEqual([$global->email]);
});

it('apply returns only owner rows when owner is resolved and model has no scopeForOwner', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000b');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $tableName = (new Customer)->getTable();

    // Bypass model events (which may auto-assign owner columns) to create a truly-global row.
    DB::table($tableName)->insert([
        'id' => (string) Str::uuid(),
        'first_name' => 'Global',
        'last_name' => 'Customer',
        'email' => 'global2@example.com',
        'status' => 'active',
        'accepts_marketing' => 0,

        'owner_type' => null,
        'owner_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    OwnerContext::withOwner($ownerA, fn (): Customer => Customer::query()->create([
        'first_name' => 'A',
        'last_name' => 'Customer',
        'email' => 'a@example.com',
        'status' => 'active',
        'accepts_marketing' => false,

        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]));

    OwnerContext::withOwner($ownerB, fn (): Customer => Customer::query()->create([
        'first_name' => 'B',
        'last_name' => 'Customer',
        'email' => 'b@example.com',
        'status' => 'active',
        'accepts_marketing' => false,

        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]));

    $noScopeModel = new class extends Model {};
    $noScopeModel->setTable($tableName);

    $emails = OwnerUiScope::apply($noScopeModel->newQuery(), configKey: 'customers.features.owner', includeGlobal: false)
        ->orderBy('email')
        ->pluck('email')
        ->all();

    expect($emails)->toEqual(['a@example.com']);
});
