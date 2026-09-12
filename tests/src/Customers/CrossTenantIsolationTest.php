<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Actions\AssignCustomerToSegment;
use AIArmada\Customers\Actions\RebuildAllSegments;
use AIArmada\Customers\Actions\RemoveCustomerFromSegment;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Services\SegmentationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

require_once __DIR__ . '/Fixtures/CustomersTestOwner.php';

/**
 * @param  array<string, mixed>  $attributes
 */
function createIsolationCustomer(array $attributes, ?Model $owner = null): Customer
{
    /** @var Customer $customer */
    $customer = OwnerContext::withOwner($owner, fn (): Customer => Customer::query()->create($attributes));

    return $customer;
}

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

it('does not match customers across tenant boundary', function (): void {
    $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

    $customerA = createIsolationCustomer([
        'first_name' => 'A',
        'last_name' => 'Customer',
        'email' => 'a-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'accepts_marketing' => true,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ], $ownerA);

    $customerB = createIsolationCustomer([
        'first_name' => 'B',
        'last_name' => 'Customer',
        'email' => 'b-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'accepts_marketing' => true,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ], $ownerB);

    $segmentA = OwnerContext::withOwner($ownerA, fn (): Segment => Segment::query()->create([
        'name' => 'Marketing Segment A',
        'slug' => 'marketing-segment-a-' . uniqid(),
        'is_active' => true,
        'is_automatic' => true,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'conditions' => [
            ['field' => 'accepts_marketing', 'value' => true],
        ],
    ]));

    $matchedIds = $segmentA->getMatchingCustomers()->pluck('id')->all();

    expect($matchedIds)
        ->toContain($customerA->id)
        ->not->toContain($customerB->id);
});

it('prevents cross-tenant segment membership changes', function (): void {
    $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

    $customerB = createIsolationCustomer([
        'first_name' => 'B',
        'last_name' => 'Customer',
        'email' => 'b2-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ], $ownerB);

    $segmentA = OwnerContext::withOwner($ownerA, fn (): Segment => Segment::query()->create([
        'name' => 'Segment A',
        'slug' => 'segment-a-' . uniqid(),
        'is_active' => true,
        'is_automatic' => false,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]));

    $service = new SegmentationService(
        new AssignCustomerToSegment,
        new RemoveCustomerFromSegment,
        new RebuildAllSegments,
    );

    expect($service->addToSegment($customerB, $segmentA))->toBeFalse();

    $service->evaluateCustomer($customerB);

    expect($customerB->segments()->whereKey($segmentA->getKey())->exists())->toBeFalse();
});

it('enforces owner scoping for addresses', function (): void {
    $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

    $customerA = createIsolationCustomer([
        'first_name' => 'A',
        'last_name' => 'Customer',
        'email' => 'addr-a-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ], $ownerA);

    $customerB = createIsolationCustomer([
        'first_name' => 'B',
        'last_name' => 'Customer',
        'email' => 'addr-b-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ], $ownerB);

    $addressA = OwnerContext::withOwner($ownerA, function (): Address {
        return Address::query()->create([
            'line1' => '123 Owner A',
            'city' => 'KL',
            'postcode' => '50000',
            'country' => 'MY',
        ]);
    });
    OwnerContext::withOwner($ownerA, fn (): mixed => $customerA->attachAddress($addressA, type: 'shipping'));

    $addressB = OwnerContext::withOwner($ownerB, function (): Address {
        return Address::query()->create([
            'line1' => '456 Owner B',
            'city' => 'KL',
            'postcode' => '50000',
            'country' => 'MY',
        ]);
    });
    OwnerContext::withOwner($ownerB, fn (): mixed => $customerB->attachAddress($addressB, type: 'shipping'));

    OwnerContext::withOwner($ownerA, function () use ($addressB, $customerA): void {
        expect(Address::query()->count())->toBe(1);

        expect(fn () => $customerA->attachAddress($addressB, type: 'shipping'))
            ->toThrow(AuthorizationException::class);
    });
});
