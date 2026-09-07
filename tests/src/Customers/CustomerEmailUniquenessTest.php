<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__ . '/Fixtures/CustomersTestOwner.php';

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

it('allows normalized duplicate emails across different owners', function (): void {
    $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

    $customerA = OwnerContext::withOwner($ownerA, fn (): Customer => Customer::query()->create([
        'first_name' => 'Owner',
        'last_name' => 'A',
        'email' => '  Shared@example.com  ',
    ]));

    $customerB = OwnerContext::withOwner($ownerB, fn (): Customer => Customer::query()->create([
        'first_name' => 'Owner',
        'last_name' => 'B',
        'email' => 'shared@example.com',
    ]));

    expect($customerA->getKey())->not->toBe($customerB->getKey())
        ->and(Customer::query()->forOwner($ownerA)->whereRaw('LOWER(TRIM(email)) = ?', ['shared@example.com'])->count())->toBe(1)
        ->and(Customer::query()->forOwner($ownerB)->whereRaw('LOWER(TRIM(email)) = ?', ['shared@example.com'])->count())->toBe(1);
});

it('lets the database reject a competing duplicate after model validation is bypassed', function (): void {
    $owner = CustomersTestOwner::query()->create(['name' => 'Owner']);
    $tableName = (new Customer)->getTable();
    $ownerType = $owner->getMorphClass();
    $ownerId = $owner->getKey();

    OwnerContext::withOwner($owner, function () use ($ownerId, $ownerType, $tableName): void {
        DB::table($tableName)->insert([
            'id' => (string) Str::uuid(),
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'first_name' => 'First',
            'last_name' => 'Customer',
            'email' => '  Race@example.com ',
            'status' => 'active',
            'accepts_marketing' => true,
            'is_guest' => true,
        ]);

        expect(fn () => DB::table($tableName)->insert([
            'id' => (string) Str::uuid(),
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'first_name' => 'Second',
            'last_name' => 'Customer',
            'email' => 'race@example.com',
            'status' => 'active',
            'accepts_marketing' => true,
            'is_guest' => true,
        ]))->toThrow(QueryException::class);
    });
});

it('keeps global email uniqueness separate from owned email uniqueness', function (): void {
    config()->set('customers.features.owner.include_global', true);

    $owner = CustomersTestOwner::query()->create(['name' => 'Owner']);
    $email = 'global@example.com';

    $globalCustomer = OwnerContext::withOwner(null, fn (): Customer => Customer::query()->create([
        'first_name' => 'Global',
        'last_name' => 'Customer',
        'email' => '  Global@Example.com ',
    ]));

    $ownedCustomer = OwnerContext::withOwner($owner, fn (): Customer => Customer::query()->create([
        'first_name' => 'Owned',
        'last_name' => 'Customer',
        'email' => $email,
    ]));

    expect($globalCustomer->isGlobal())->toBeTrue()
        ->and($ownedCustomer->belongsToOwner($owner))->toBeTrue()
        ->and(fn () => OwnerContext::withOwner(null, fn (): Customer => Customer::query()->create([
            'first_name' => 'Duplicate',
            'last_name' => 'Global',
            'email' => 'GLOBAL@example.com',
        ])))->toThrow(ValidationException::class);
});

it('blocks the uniqueness migration before creating indexes when duplicates exist', function (): void {
    $tableName = 'customer_email_preflight_' . Str::lower(Str::random(8));

    Schema::create($tableName, function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('email')->nullable();
        $table->string('owner_type')->nullable();
        $table->uuid('owner_id')->nullable();
    });

    $ownerId = (string) Str::uuid();

    DB::table($tableName)->insert([
        [
            'id' => (string) Str::uuid(),
            'email' => '  Duplicate@example.com ',
            'owner_type' => CustomersTestOwner::class,
            'owner_id' => $ownerId,
        ],
        [
            'id' => (string) Str::uuid(),
            'email' => 'duplicate@example.com',
            'owner_type' => CustomersTestOwner::class,
            'owner_id' => $ownerId,
        ],
    ]);

    config()->set('customers.database.tables.customers', $tableName);

    $migration = require dirname(__DIR__, 3) . '/packages/customers/database/migrations/2026_09_07_120000_add_owner_email_uniqueness_to_customers_table.php';

    expect(fn () => $migration->up())
        ->toThrow(
            RuntimeException::class,
            '1 duplicate normalized-email groups covering 2 rows',
        );

    Schema::dropIfExists($tableName);
    config()->set('customers.database.tables.customers', 'customers');
});
