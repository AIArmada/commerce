<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Support\PrimaryEmailResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
    config()->set('contacting.features.owner.enabled', true);
    config()->set('contacting.features.owner.include_global', false);
});

function filamentCustomers_addEmail(Customer $customer, string $email, bool $primary = false, int $sortOrder = 0, ?CarbonImmutable $validFrom = null, ?CarbonImmutable $validUntil = null): void
{
    $customer->contactMethods()->create([
        'type' => 'email',
        'value' => $email,
        'normalized_value' => mb_strtolower($email),
        'is_primary' => $primary,
        'sort_order' => $sortOrder,
        'valid_from' => $validFrom,
        'valid_until' => $validUntil,
    ]);
}

function filamentCustomers_makeMailCustomer(Model $owner, string $firstName): Customer
{
    return OwnerContext::withOwner($owner, fn (): Customer => Customer::query()->create([
        'first_name' => $firstName,
        'last_name' => 'Customer',
        'status' => 'active',
        'accepts_marketing' => false,
    ]));
}

it('prefers the primary email, then the lowest sort order', function (): void {
    $owner = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    OwnerContext::withOwner($owner, function () use ($owner): void {
        $customer = filamentCustomers_makeMailCustomer($owner, 'Email');

        filamentCustomers_addEmail($customer, 'second@example.com', sortOrder: 5);
        filamentCustomers_addEmail($customer, 'primary@example.com', primary: true, sortOrder: 10);

        expect(PrimaryEmailResolver::resolve($customer))->toBe('primary@example.com');

        $unprimaried = filamentCustomers_makeMailCustomer($owner, 'Plain');

        filamentCustomers_addEmail($unprimaried, 'later@example.com', sortOrder: 5);
        filamentCustomers_addEmail($unprimaried, 'earlier@example.com', sortOrder: 1);

        expect(PrimaryEmailResolver::resolve($unprimaried))->toBe('earlier@example.com');
    });
});

it('skips emails outside their validity window and returns null when none qualify', function (): void {
    $owner = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');
    $now = CarbonImmutable::now();

    OwnerContext::withOwner($owner, function () use ($owner, $now): void {
        $customer = filamentCustomers_makeMailCustomer($owner, 'Window');

        filamentCustomers_addEmail($customer, 'expired@example.com', primary: true, validUntil: $now->subDay());
        filamentCustomers_addEmail($customer, 'future@example.com', validFrom: $now->addDay());
        filamentCustomers_addEmail($customer, 'current@example.com');

        expect(PrimaryEmailResolver::resolve($customer))->toBe('current@example.com');

        $empty = filamentCustomers_makeMailCustomer($owner, 'NoMail');

        expect(PrimaryEmailResolver::resolve($empty))->toBeNull();
    });
});

it('resolves from the loaded relation without extra queries', function (): void {
    $owner = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    OwnerContext::withOwner($owner, function () use ($owner): void {
        $customer = filamentCustomers_makeMailCustomer($owner, 'Loaded');

        filamentCustomers_addEmail($customer, 'loaded@example.com', primary: true);

        $customer->load('contactMethods');

        DB::enableQueryLog();
        DB::flushQueryLog();

        $email = PrimaryEmailResolver::resolve($customer);

        expect($email)->toBe('loaded@example.com')
            ->and(DB::getQueryLog())->toBeEmpty();

        DB::disableQueryLog();
    });
});
