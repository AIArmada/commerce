<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use AIArmada\FilamentCustomers\Resources\CustomerResource;
use Illuminate\Database\Eloquent\Model;
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

beforeEach(function (): void {
    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);
});

it('syncManualSegments keeps automatic segments and only syncs manual segments for the current owner', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    $customer = OwnerContext::withOwner($ownerA, fn (): Customer => Customer::query()->create([
        'first_name' => 'Manual',
        'last_name' => 'Assignment',
        'email' => 'manual-assignment-' . uniqid() . '@example.com',
        'status' => 'active',
        'accepts_marketing' => true,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]));

    $automaticSegment = OwnerContext::withOwner($ownerA, fn (): Segment => Segment::query()->create([
        'name' => 'Auto Segment',
        'slug' => 'auto-segment-' . uniqid(),
        'type' => 'custom',
        'is_automatic' => true,
        'is_active' => true,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]));

    $manualSegmentToKeep = OwnerContext::withOwner($ownerA, fn (): Segment => Segment::query()->create([
        'name' => 'Manual Segment Keep',
        'slug' => 'manual-keep-' . uniqid(),
        'type' => 'custom',
        'is_automatic' => false,
        'is_active' => true,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]));

    $manualSegmentToDrop = OwnerContext::withOwner($ownerA, fn (): Segment => Segment::query()->create([
        'name' => 'Manual Segment Drop',
        'slug' => 'manual-drop-' . uniqid(),
        'type' => 'custom',
        'is_automatic' => false,
        'is_active' => true,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]));

    OwnerContext::withOwner($ownerA, function () use ($customer, $automaticSegment, $manualSegmentToDrop): void {
        $customer->segments()->sync([$automaticSegment->id, $manualSegmentToDrop->id]);
    });

    OwnerContext::withOwner($ownerA, function () use ($customer, $manualSegmentToKeep): void {
        CustomerResource::syncManualSegments($customer, [$manualSegmentToKeep->id]);
    });

    $segmentIds = OwnerContext::withOwner($ownerA, fn (): array => $customer->fresh()->segments()->pluck('id')->all());

    expect($segmentIds)
        ->toContain($automaticSegment->id)
        ->toContain($manualSegmentToKeep->id)
        ->not->toContain($manualSegmentToDrop->id);
});

it('syncManualSegments rejects manual segment ids outside the current owner scope', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000b');

    $customer = OwnerContext::withOwner($ownerA, fn (): Customer => Customer::query()->create([
        'first_name' => 'Cross',
        'last_name' => 'Tenant',
        'email' => 'cross-tenant-' . uniqid() . '@example.com',
        'status' => 'active',
        'accepts_marketing' => true,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]));

    $ownerASegment = OwnerContext::withOwner($ownerA, fn (): Segment => Segment::query()->create([
        'name' => 'Owner A Manual',
        'slug' => 'owner-a-manual-' . uniqid(),
        'type' => 'custom',
        'is_automatic' => false,
        'is_active' => true,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]));

    $ownerBSegment = OwnerContext::withOwner($ownerB, fn (): Segment => Segment::query()->create([
        'name' => 'Owner B Manual',
        'slug' => 'owner-b-manual-' . uniqid(),
        'type' => 'custom',
        'is_automatic' => false,
        'is_active' => true,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]));

    expect(fn (): mixed => OwnerContext::withOwner($ownerA, fn (): mixed => CustomerResource::syncManualSegments($customer, [
        $ownerASegment->id,
        $ownerBSegment->id,
    ])))->toThrow(HttpException::class);
});

it('syncManualSegments rejects syncing when the customer record is outside current owner scope', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000b');

    $customerOutsideScope = OwnerContext::withOwner($ownerB, fn (): Customer => Customer::query()->create([
        'first_name' => 'Outside',
        'last_name' => 'Scope',
        'email' => 'outside-scope-' . uniqid() . '@example.com',
        'status' => 'active',
        'accepts_marketing' => true,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]));

    $ownerASegment = OwnerContext::withOwner($ownerA, fn (): Segment => Segment::query()->create([
        'name' => 'Owner A Manual',
        'slug' => 'owner-a-manual-record-check-' . uniqid(),
        'type' => 'custom',
        'is_automatic' => false,
        'is_active' => true,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]));

    expect(fn (): mixed => OwnerContext::withOwner($ownerA, fn (): mixed => CustomerResource::syncManualSegments($customerOutsideScope, [
        $ownerASegment->id,
    ])))->toThrow(HttpException::class);
});
