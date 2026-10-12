<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\FilamentCustomersServiceProvider;
use AIArmada\FilamentCustomers\Pages\AddressValidationPage;
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

if (! function_exists('filamentCustomers_makeCustomerAddress')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function filamentCustomers_makeCustomerAddress(Model $owner, array $attributes): Address
    {
        return OwnerContext::withOwner($owner, function () use ($attributes): Address {
            $customer = Customer::query()->create([
                'first_name' => 'Scoped',
                'last_name' => 'Customer',
            ]);
            $address = Address::query()->create($attributes);
            $customer->attachAddress($address, type: 'shipping');

            return $address;
        });
    }
}

beforeEach(function (): void {
    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);
});

it('lists only invalid addresses inside the current owner scope', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000b');

    $invalid = filamentCustomers_makeCustomerAddress($ownerA, [
        'line1' => 'Line 1',
        'city' => 'Kuala Lumpur',
        'postcode' => 'ABC',
        'country_code' => 'MY',
    ]);

    filamentCustomers_makeCustomerAddress($ownerA, [
        'line1' => 'Line 2',
        'city' => 'Kuala Lumpur',
        'state' => 'Kuala Lumpur',
        'postcode' => '50450',
        'country_code' => 'MY',
    ]);

    filamentCustomers_makeCustomerAddress($ownerB, [
        'line1' => 'Line 3',
        'city' => 'Kuala Lumpur',
        'postcode' => 'XYZ',
        'country_code' => 'MY',
    ]);

    $listed = OwnerContext::withOwner(
        $ownerA,
        fn (): array => (new AddressValidationPage)->getInvalidAddresses(),
    );

    expect($listed)->toHaveCount(1)
        ->and($listed[0]['id'])->toBe($invalid->id)
        ->and($listed[0]['violations'])->not->toBeEmpty();
});

it('registers the address-validation blade view', function (): void {
    $this->app->register(FilamentCustomersServiceProvider::class);

    expect(view()->exists('filament-customers::pages.address-validation'))->toBeTrue();
});
