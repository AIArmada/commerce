<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Customers\Models\Customer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

it('ignores native contact attributes passed to mass assignment', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Canonical',
        'last_name' => 'Customer',
        'email' => 'ignored@example.com',
        'phone' => '+60123456789',
    ]);

    expect($customer->getAttributes())->not->toHaveKeys(['email', 'phone'])
        ->and($customer->resolveEmail())->toBeNull()
        ->and($customer->resolvePhone())->toBeNull();
});

it('allows normalized duplicate emails across different owners', function (): void {
    $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

    $customerA = OwnerContext::withOwner($ownerA, fn (): Customer => Customer::query()->create([
        'first_name' => 'Owner',
        'last_name' => 'A',
    ]));
    OwnerContext::withOwner($ownerA, function () use ($customerA): void {
        $customerA->addContactMethod(ContactMethodData::email('  Shared@example.com  '));
    });

    $customerB = OwnerContext::withOwner($ownerB, fn (): Customer => Customer::query()->create([
        'first_name' => 'Owner',
        'last_name' => 'B',
    ]));
    OwnerContext::withOwner($ownerB, function () use ($customerB): void {
        $customerB->addContactMethod(ContactMethodData::email('shared@example.com'));
    });

    expect($customerA->getKey())->not->toBe($customerB->getKey())
        ->and(OwnerContext::withOwner($ownerA, fn (): ?string => $customerA->resolveEmail()))->toBe('shared@example.com')
        ->and(OwnerContext::withOwner($ownerB, fn (): ?string => $customerB->resolveEmail()))->toBe('shared@example.com');
});

it('rejects a competing canonical email within one owner', function (): void {
    $owner = CustomersTestOwner::query()->create(['name' => 'Owner']);

    OwnerContext::withOwner($owner, function (): void {
        $first = Customer::query()->create([
            'first_name' => 'First',
            'last_name' => 'Customer',
        ]);
        $first->addContactMethod(ContactMethodData::email('  Race@example.com '));

        $second = Customer::query()->create([
            'first_name' => 'Second',
            'last_name' => 'Customer',
        ]);

        expect(fn () => $second->addContactMethod(ContactMethodData::email('race@example.com')))
            ->toThrow(ValidationException::class);
    });
});

it('keeps global email uniqueness separate from owned email uniqueness', function (): void {
    config()->set('customers.features.owner.include_global', true);

    $owner = CustomersTestOwner::query()->create(['name' => 'Owner']);
    $email = 'global@example.com';

    $globalCustomer = OwnerContext::withOwner(null, fn (): Customer => Customer::query()->create([
        'first_name' => 'Global',
        'last_name' => 'Customer',
    ]));
    OwnerContext::withOwner(null, function () use ($globalCustomer): void {
        $globalCustomer->addContactMethod(ContactMethodData::email('  Global@Example.com '));
    });

    $ownedCustomer = OwnerContext::withOwner($owner, fn (): Customer => Customer::query()->create([
        'first_name' => 'Owned',
        'last_name' => 'Customer',
    ]));
    OwnerContext::withOwner($owner, function () use ($email, $ownedCustomer): void {
        $ownedCustomer->addContactMethod(ContactMethodData::email($email));
    });

    expect($globalCustomer->isGlobal())->toBeTrue()
        ->and($ownedCustomer->belongsToOwner($owner))->toBeTrue()
        ->and(fn () => OwnerContext::withOwner(null, function (): void {
            $duplicate = Customer::query()->create([
                'first_name' => 'Duplicate',
                'last_name' => 'Global',
            ]);
            $duplicate->addContactMethod(ContactMethodData::email('GLOBAL@example.com'));
        }))->toThrow(ValidationException::class);
});
