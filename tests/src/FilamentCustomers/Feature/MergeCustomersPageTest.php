<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Pages\MergeCustomersPage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

if (! function_exists('filamentCustomers_makeUser')) {
    function filamentCustomers_makeUser(string $prefix): User
    {
        return User::query()->create([
            'name' => 'Segment Admin',
            'email' => $prefix . '-' . uniqid() . '@example.com',
            'password' => 'password',
        ]);
    }
}

if (! function_exists('filamentCustomers_makeCustomer')) {
    function filamentCustomers_makeCustomer(Model $owner, string $firstName, string $lastName = 'Merge'): Customer
    {
        return OwnerContext::withOwner($owner, fn (): Customer => Customer::query()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'status' => 'active',
            'accepts_marketing' => false,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]));
    }
}

if (! function_exists('filamentCustomers_invokeMergeCustomers')) {
    function filamentCustomers_invokeMergeCustomers(string $targetId, string $sourceId): void
    {
        $method = new ReflectionMethod(MergeCustomersPage::class, 'mergeCustomers');
        $method->setAccessible(true);
        $method->invoke(new MergeCustomersPage, $targetId, $sourceId);
    }
}

if (! function_exists('filamentCustomers_searchCustomers')) {
    function filamentCustomers_searchCustomers(string $search): array
    {
        $method = new ReflectionMethod(MergeCustomersPage::class, 'searchCustomers');
        $method->setAccessible(true);

        /** @var array<string, string> $results */
        $results = $method->invoke(new MergeCustomersPage, $search);

        return $results;
    }
}

beforeEach(function (): void {
    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);
});

it('merges two in-scope customers for an authorized user', function (): void {
    // A real User owner: the core merge action resolves the `owner` morph
    // relation, which the anonymous fake owner cannot satisfy.
    $owner = filamentCustomers_makeUser('merge-owner');

    $user = filamentCustomers_makeUser('merge');

    test()->actingAs($user);

    Gate::before(fn (mixed $authenticatedUser, string $ability): ?bool => $authenticatedUser?->is($user) === true
        && in_array($ability, ['customers.customers.update', 'customers.customers.delete'], true)
        ? true
        : null);

    $target = filamentCustomers_makeCustomer($owner, 'Keep');
    $source = filamentCustomers_makeCustomer($owner, 'Drop');

    OwnerContext::withOwner($owner, fn (): mixed => filamentCustomers_invokeMergeCustomers($target->id, $source->id));

    expect(Customer::query()->withoutOwnerScope()->whereKey($target->id)->exists())->toBeTrue()
        ->and(Customer::query()->withoutOwnerScope()->whereKey($source->id)->exists())->toBeFalse();
});

it('refuses to merge a customer into itself without touching data', function (): void {
    $owner = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    $customer = filamentCustomers_makeCustomer($owner, 'Solo');

    OwnerContext::withOwner($owner, fn (): mixed => filamentCustomers_invokeMergeCustomers($customer->id, $customer->id));

    expect(Customer::query()->withoutOwnerScope()->whereKey($customer->id)->exists())->toBeTrue();
});

it('converts cross-owner merge lookups into a no-op instead of a 500', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000b');

    test()->actingAs(filamentCustomers_makeUser('merge-cross'));

    $target = filamentCustomers_makeCustomer($ownerA, 'Keep');
    $source = filamentCustomers_makeCustomer($ownerB, 'Foreign');

    OwnerContext::withOwner($ownerA, fn (): mixed => filamentCustomers_invokeMergeCustomers($target->id, $source->id));

    expect(Customer::query()->withoutOwnerScope()->whereKey($target->id)->exists())->toBeTrue()
        ->and(Customer::query()->withoutOwnerScope()->whereKey($source->id)->exists())->toBeTrue();
});

it('requires authentication to merge', function (): void {
    $owner = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    $target = filamentCustomers_makeCustomer($owner, 'Keep');
    $source = filamentCustomers_makeCustomer($owner, 'Drop');

    expect(fn (): mixed => OwnerContext::withOwner($owner, fn (): mixed => filamentCustomers_invokeMergeCustomers($target->id, $source->id)))
        ->toThrow(HttpException::class);
});

it('denies merges without the update ability on both records', function (): void {
    $owner = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    test()->actingAs(filamentCustomers_makeUser('merge-denied'));

    Gate::before(fn (mixed $user, string $ability): ?bool => $ability === 'update' ? false : null);

    $target = filamentCustomers_makeCustomer($owner, 'Keep');
    $source = filamentCustomers_makeCustomer($owner, 'Drop');

    expect(fn (): mixed => OwnerContext::withOwner($owner, fn (): mixed => filamentCustomers_invokeMergeCustomers($target->id, $source->id)))
        ->toThrow(AuthorizationException::class);

    expect(Customer::query()->withoutOwnerScope()->whereKey($source->id)->exists())->toBeTrue();
});

it('matches LIKE wildcards literally in merge search', function (): void {
    $owner = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    OwnerContext::withOwner($owner, function () use ($owner): void {
        $literal = filamentCustomers_makeCustomer($owner, 'Deal 100%', 'Percent');
        $decoy = filamentCustomers_makeCustomer($owner, 'Deal 100X', 'Ex');

        $results = filamentCustomers_searchCustomers('100%');

        expect($results)->toHaveKey($literal->id)
            ->and($results)->not->toHaveKey($decoy->id);

        $underscored = filamentCustomers_makeCustomer($owner, 'a_b', 'Under');
        $noUnderscore = filamentCustomers_makeCustomer($owner, 'aXb', 'NoUnder');

        $underscoreResults = filamentCustomers_searchCustomers('a_b');

        expect($underscoreResults)->toHaveKey($underscored->id)
            ->and($underscoreResults)->not->toHaveKey($noUnderscore->id);
    });
});

it('labels merge search results without per-row lookups', function (): void {
    $owner = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    OwnerContext::withOwner($owner, function () use ($owner): void {
        foreach (range(1, 6) as $index) {
            filamentCustomers_makeCustomer($owner, "Searchable{$index}");
        }

        $queries = [];

        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $results = filamentCustomers_searchCustomers('Searchable');

        expect($results)->toHaveCount(6)
            ->and($queries)->toHaveCount(2);

        foreach ($results as $label) {
            expect($label)->toContain('Searchable');
        }
    });
});
