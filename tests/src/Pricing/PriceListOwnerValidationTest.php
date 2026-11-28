<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Features\Segmentation\Models\Segment;
use AIArmada\Customers\Models\Customer;
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

it('enforces pricing ownership even when customer owner scoping is disabled', function (): void {
    $owner = User::query()->create([
        'name' => 'Pricing Reference Owner',
        'email' => 'pricing-disabled-scope-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    [$customer, $segment] = OwnerContext::withOwner($owner, static fn (): array => [
        Customer::query()->create([
            'first_name' => 'Private',
            'last_name' => 'Customer',
            'status' => CustomerStatus::Active,
        ]),
        Segment::query()->create([
            'name' => 'Private Segment',
            'slug' => 'private-pricing-segment-' . uniqid(),
        ]),
    ]);

    config(['customers.features.owner.enabled' => false]);

    OwnerContext::withOwner(null, function () use ($customer, $segment): void {
        foreach (['customer_id' => $customer->id, 'segment_id' => $segment->id] as $attribute => $id) {
            expect(fn () => PriceList::query()->create([
                'name' => 'Invalid Global List',
                'slug' => 'invalid-global-' . uniqid(),
                'currency' => 'MYR',
                $attribute => $id,
            ]))->toThrow(AuthorizationException::class, $attribute);
        }
    });

    $list = OwnerContext::withOwner($owner, fn (): PriceList => PriceList::query()->create([
        'name' => 'Owned List',
        'slug' => 'valid-owned-' . uniqid(),
        'currency' => 'MYR',
        'customer_id' => $customer->id,
        'segment_id' => $segment->id,
    ]));

    expect($list->exists)->toBeTrue();
});
