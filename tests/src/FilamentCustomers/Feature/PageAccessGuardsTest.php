<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use AIArmada\FilamentCustomers\Pages\MergeCustomersPage;
use AIArmada\FilamentCustomers\Pages\SegmentRebuildPage;
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

it('blocks merge page customer lookups outside the current owner scope', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000b');

    $foreignCustomer = OwnerContext::withOwner($ownerB, fn (): Customer => Customer::query()->create([
        'first_name' => 'Foreign',
        'last_name' => 'Customer',
        'email' => 'foreign-' . uniqid() . '@example.com',
        'status' => 'active',
        'accepts_marketing' => false,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]));

    $page = new MergeCustomersPage;
    $method = new ReflectionMethod($page, 'getCustomerLabel');
    $method->setAccessible(true);

    expect(fn (): mixed => OwnerContext::withOwner($ownerA, fn (): mixed => $method->invoke($page, $foreignCustomer->id)))
        ->toThrow(AuthorizationException::class);
});

it('blocks segment rebuilds outside the current owner scope', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000b');

    $foreignSegment = OwnerContext::withOwner($ownerB, fn (): Segment => Segment::query()->create([
        'name' => 'Foreign Segment',
        'slug' => 'foreign-segment-' . uniqid(),
        'type' => 'custom',
        'is_automatic' => true,
        'is_active' => true,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]));

    expect(fn (): mixed => OwnerContext::withOwner($ownerA, fn (): mixed => (new SegmentRebuildPage)->rebuildSegment($foreignSegment->id)))
        ->toThrow(AuthorizationException::class);
});
