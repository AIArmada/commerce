<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Actions\CreateCustomer;
use AIArmada\Customers\Actions\LinkCustomerToPerson;
use AIArmada\Customers\Actions\UpdateCustomerProfile;
use AIArmada\Customers\Models\Customer;
use AIArmada\Persons\Models\Person;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;

it('pins the indexed nullable customer person link', function (): void {
    $table = (new Customer)->getTable();

    expect(Schema::hasColumn($table, 'person_id'))->toBeTrue()
        ->and(Schema::hasIndex($table, 'customers_person_id_index'))->toBeTrue();
});

it('links an owner-scoped customer to the shared person identity', function (): void {
    [$customer, $person] = OwnerContext::withOwner(null, function (): array {
        $customer = Customer::create([
            'first_name' => 'Linked',
            'last_name' => 'Customer',
            'email' => 'linked-customer-' . uniqid() . '@example.com',
        ]);
        $person = Person::create(['name' => 'Linked Customer']);

        return [$customer, $person];
    });

    $linked = OwnerContext::withOwner(null, fn (): Customer => app(LinkCustomerToPerson::class)->execute($customer, $person));

    expect($linked->person_id)->toBe($person->getKey())
        ->and($linked->person?->is($person))->toBeTrue();
});

it('links a person when CreateCustomer receives a person key', function (): void {
    [$customer, $person] = OwnerContext::withOwner(null, function (): array {
        $person = Person::create(['name' => 'Created Customer Person']);
        $customer = (new CreateCustomer)->execute(
            'created-person-' . uniqid() . '@example.com',
            ['first_name' => 'Created', 'last_name' => 'Customer'],
            [],
            null,
            true,
            $person->getKey(),
        );

        return [$customer, $person];
    });

    expect($customer->person_id)->toBe($person->getKey());
});

it('links a person when UpdateCustomerProfile receives a person key', function (): void {
    [$customer, $person] = OwnerContext::withOwner(null, function (): array {
        $customer = Customer::create([
            'first_name' => 'Updated',
            'last_name' => 'Customer',
            'email' => 'updated-person-' . uniqid() . '@example.com',
        ]);
        $person = Person::create(['name' => 'Updated Customer Person']);

        (new UpdateCustomerProfile)->execute($customer, [], [], null, $person->getKey());

        return [$customer, $person];
    });

    expect($customer->fresh()?->person_id)->toBe($person->getKey());
});

it('rejects an unknown person key during customer creation', function (): void {
    expect(fn (): Customer => OwnerContext::withOwner(null, fn (): Customer => (new CreateCustomer)->execute(
        'unknown-person-' . uniqid() . '@example.com',
        ['first_name' => 'Unknown', 'last_name' => 'Person'],
        [],
        null,
        true,
        'missing-person-id',
    )))->toThrow(ModelNotFoundException::class);
});

it('keeps an existing person link when an update omits personId', function (): void {
    $customer = OwnerContext::withOwner(null, function (): Customer {
        $person = Person::create(['name' => 'Persistent Customer Person']);

        return (new CreateCustomer)->execute(
            'persistent-person-' . uniqid() . '@example.com',
            ['first_name' => 'Persistent', 'last_name' => 'Customer'],
            [],
            null,
            true,
            $person->getKey(),
        );
    });

    $personId = $customer->person_id;

    OwnerContext::withOwner(null, function () use ($customer): void {
        (new UpdateCustomerProfile)->execute(
            $customer,
            ['first_name' => 'Updated'],
            [],
            null,
        );
    });

    expect($customer->fresh()?->person_id)->toBe($personId);
});
