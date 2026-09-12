<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Pages\AddressValidationPage;
use Illuminate\Auth\Access\AuthorizationException;
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

beforeEach(function (): void {
    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);
});

it('only validates addresses inside the current owner scope', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000b');

    $addressInScope = OwnerContext::withOwner($ownerA, function (): Address {
        $customer = Customer::query()->create([
            'first_name' => 'Scoped',
            'last_name' => 'Customer',
        ]);
        $address = Address::query()->create([
            'line1' => 'Line 1',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'MY',
        ]);
        $customer->attachAddress($address, type: 'shipping');

        return $address;
    });

    $addressOutOfScope = OwnerContext::withOwner($ownerB, function (): Address {
        $customer = Customer::query()->create([
            'first_name' => 'Other',
            'last_name' => 'Customer',
        ]);
        $address = Address::query()->create([
            'line1' => 'Line 2',
            'city' => 'City',
            'postcode' => '54321',
            'country' => 'MY',
        ]);
        $customer->attachAddress($address, type: 'shipping');

        return $address;
    });

    $page = new AddressValidationPage;

    OwnerContext::withOwner($ownerA, function () use ($addressInScope, $page): void {
        $page->validateAddress($addressInScope->id);
    });

    expect($addressInScope->fresh()->validated_at)->not->toBeNull()
        ->and($addressInScope->fresh()->validation_status)->toBe('verified');

    expect(fn (): mixed => OwnerContext::withOwner($ownerA, fn (): mixed => $page->validateAddress($addressOutOfScope->id)))
        ->toThrow(AuthorizationException::class);
});
