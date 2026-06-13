<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Address;
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

    $addressInScope = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'customer_id' => OwnerContext::withOwner($ownerA, fn (): string => Customer::query()->create([
            'first_name' => 'Scoped',
            'last_name' => 'Customer',
            'email' => 'scoped-' . uniqid() . '@example.com',
            'status' => 'active',
            'accepts_marketing' => false,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ])->id),
        'type' => 'both',
        'line1' => 'Line 1',
        'city' => 'City',
        'postcode' => '12345',
        'country' => 'MY',
        'is_default_billing' => false,
        'is_default_shipping' => false,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]));

    $addressOutOfScope = OwnerContext::withOwner($ownerB, fn (): Address => Address::query()->create([
        'customer_id' => OwnerContext::withOwner($ownerB, fn (): string => Customer::query()->create([
            'first_name' => 'Other',
            'last_name' => 'Customer',
            'email' => 'other-' . uniqid() . '@example.com',
            'status' => 'active',
            'accepts_marketing' => false,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
        ])->id),
        'type' => 'both',
        'line1' => 'Line 2',
        'city' => 'City',
        'postcode' => '54321',
        'country' => 'MY',
        'is_default_billing' => false,
        'is_default_shipping' => false,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]));

    $page = new AddressValidationPage;

    OwnerContext::withOwner($ownerA, function () use ($addressInScope, $page): void {
        $page->validateAddress($addressInScope->id);
    });

    expect($addressInScope->fresh()->verified_at)->not->toBeNull();

    expect(fn (): mixed => OwnerContext::withOwner($ownerA, fn (): mixed => $page->validateAddress($addressOutOfScope->id)))
        ->toThrow(AuthorizationException::class);
});
