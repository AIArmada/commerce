<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Actions\LinkCustomerToPerson;
use AIArmada\Customers\Models\Customer;
use AIArmada\Persons\Models\Person;
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
