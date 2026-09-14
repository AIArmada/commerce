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
    function filamentCustomers_makeCustomerAddress(Model $owner, string $line1): Address
    {
        return OwnerContext::withOwner($owner, function () use ($line1): Address {
            $customer = Customer::query()->create([
                'first_name' => 'Batch',
                'last_name' => 'Customer',
            ]);

            $address = Address::query()->create([
                'line1' => $line1,
                'city' => 'City',
                'postcode' => '12345',
                'country' => 'MY',
            ]);

            $customer->attachAddress($address, type: 'shipping');

            return $address;
        });
    }
}

beforeEach(function (): void {
    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);
});

it('batch-validates only the current owner scope instead of calling a missing command', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000b');

    $inScope = filamentCustomers_makeCustomerAddress($ownerA, 'In-scope Street');
    $outOfScope = filamentCustomers_makeCustomerAddress($ownerB, 'Foreign Street');

    OwnerContext::withOwner($ownerA, fn (): mixed => (new AddressValidationPage)->runBatchValidation());

    expect($inScope->fresh()->validated_at)->not->toBeNull()
        ->and($inScope->fresh()->validation_status)->toBe('verified')
        ->and($outOfScope->fresh()->validated_at)->toBeNull();
});

it('caps batch validation at 100 addresses per run', function (): void {
    $owner = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    foreach (range(1, 102) as $index) {
        filamentCustomers_makeCustomerAddress($owner, "Street {$index}");
    }

    OwnerContext::withOwner($owner, fn (): mixed => (new AddressValidationPage)->runBatchValidation());

    $verified = OwnerContext::withOwner($owner, fn (): int => Address::query()->whereNotNull('validated_at')->count());

    expect($verified)->toBe(100);
});

it('registers the address-validation blade view', function (): void {
    $this->app->register(FilamentCustomersServiceProvider::class);

    expect(view()->exists('filament-customers::pages.address-validation'))->toBeTrue();
});
