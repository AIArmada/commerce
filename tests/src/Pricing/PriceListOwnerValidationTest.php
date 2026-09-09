<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use AIArmada\Pricing\Models\PriceList;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    config([
        'pricing.features.owner.enabled' => true,
        'pricing.features.owner.include_global' => false,
        'customers.features.owner.enabled' => true,
        'customers.features.owner.include_global' => false,
    ]);
});

it('rejects a customer reference owned by another tenant', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Pricing Owner A',
        'email' => 'pricing-reference-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Pricing Owner B',
        'email' => 'pricing-reference-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $customerB = OwnerContext::withOwner($ownerB, static fn (): Customer => Customer::query()->create([
        'first_name' => 'Tenant B',
        'last_name' => 'Customer',
        'status' => CustomerStatus::Active,
    ]));

    expect(fn () => OwnerContext::withOwner($ownerA, static fn (): PriceList => PriceList::query()->create([
        'name' => 'Invalid Customer List',
        'slug' => 'invalid-customer-' . uniqid(),
        'currency' => 'MYR',
        'customer_id' => $customerB->id,
    ])))->toThrow(AuthorizationException::class);
});

it('rejects a segment reference owned by another tenant', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Pricing Segment Owner A',
        'email' => 'pricing-segment-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Pricing Segment Owner B',
        'email' => 'pricing-segment-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $segmentB = OwnerContext::withOwner($ownerB, static fn (): Segment => Segment::query()->create([
        'name' => 'Tenant B Segment',
        'slug' => 'tenant-b-segment-' . uniqid(),
    ]));

    expect(fn () => OwnerContext::withOwner($ownerA, static fn (): PriceList => PriceList::query()->create([
        'name' => 'Invalid Segment List',
        'slug' => 'invalid-segment-' . uniqid(),
        'currency' => 'MYR',
        'segment_id' => $segmentB->id,
    ])))->toThrow(AuthorizationException::class);
});
