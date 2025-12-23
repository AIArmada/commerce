<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Address;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Models\Wishlist;
use AIArmada\Customers\Services\SegmentationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

require_once __DIR__ . '/Fixtures/CustomersTestOwner.php';

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

    $customerA = Customer::query()->create([
        'first_name' => 'A',
        'last_name' => 'Customer',
        'email' => 'a-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'lifetime_value' => 2_000_00,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $customerB = Customer::query()->create([
        'first_name' => 'B',
        'last_name' => 'Customer',
        'email' => 'b-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'lifetime_value' => 2_000_00,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $segmentA = Segment::query()->create([
        'name' => 'High LTV A',
        'slug' => 'high-ltv-a-' . uniqid(),
        'is_active' => true,
        'is_automatic' => true,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'conditions' => [
            ['field' => 'lifetime_value_min', 'value' => 1_000_00],
        ],
    ]);

    $matchedIds = $segmentA->getMatchingCustomers()->pluck('id')->all();

    expect($matchedIds)
        ->toContain($customerA->id)
        ->not->toContain($customerB->id);
});

it('prevents cross-tenant segment membership changes', function (): void {
    $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

    $customerB = Customer::query()->create([
        'first_name' => 'B',
        'last_name' => 'Customer',
        'email' => 'b2-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'lifetime_value' => 2_000_00,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $segmentA = Segment::query()->create([
        'name' => 'Segment A',
        'slug' => 'segment-a-' . uniqid(),
        'is_active' => true,
        'is_automatic' => false,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $service = new SegmentationService;

    expect($service->addToSegment($customerB, $segmentA))->toBeFalse();

    $service->evaluateCustomer($customerB);

    expect($customerB->segments()->whereKey($segmentA->getKey())->exists())->toBeFalse();
});

it('enforces owner scoping for addresses and wishlists', function (): void {
    $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

    $customerA = Customer::query()->create([
        'first_name' => 'A',
        'last_name' => 'Customer',
        'email' => 'addr-a-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $customerB = Customer::query()->create([
        'first_name' => 'B',
        'last_name' => 'Customer',
        'email' => 'addr-b-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    OwnerContext::withOwner($ownerA, function () use ($customerA): void {
        Address::query()->create([
            'customer_id' => $customerA->id,
            'address_line_1' => '123 Owner A',
            'city' => 'KL',
            'postcode' => '50000',
            'country' => 'MY',
        ]);

        Wishlist::query()->create([
            'customer_id' => $customerA->id,
            'name' => 'Owner A Wishlist',
        ]);
    });

    OwnerContext::withOwner($ownerB, function () use ($customerB): void {
        Address::query()->create([
            'customer_id' => $customerB->id,
            'address_line_1' => '456 Owner B',
            'city' => 'KL',
            'postcode' => '50000',
            'country' => 'MY',
        ]);

        Wishlist::query()->create([
            'customer_id' => $customerB->id,
            'name' => 'Owner B Wishlist',
        ]);
    });

    OwnerContext::withOwner($ownerA, function () use ($customerB): void {
        expect(Address::query()->count())->toBe(1)
            ->and(Wishlist::query()->count())->toBe(1);

        expect(fn () => Address::query()->create([
            'customer_id' => $customerB->id,
            'address_line_1' => 'Cross-tenant',
            'city' => 'KL',
            'postcode' => '50000',
            'country' => 'MY',
        ]))->toThrow(InvalidArgumentException::class);

        expect(fn () => Wishlist::query()->create([
            'customer_id' => $customerB->id,
            'name' => 'Cross-tenant wishlist',
        ]))->toThrow(InvalidArgumentException::class);
    });
});
