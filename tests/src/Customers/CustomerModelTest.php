<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;

/**
 * @param  array<string, mixed>  $attributes
 */
function createCustomerModelTestCustomer(array $attributes): Customer
{
    $restricted = [];

    foreach (['user_id', 'status', 'is_guest', 'accepts_marketing', 'created_at', 'updated_at'] as $key) {
        if (array_key_exists($key, $attributes)) {
            $restricted[$key] = $attributes[$key];
            unset($attributes[$key]);
        }
    }

    $customer = Customer::query()->create($attributes);

    if ($restricted !== []) {
        $customer->forceFill($restricted)->save();
    }

    return $customer;
}

describe('Customer Model', function (): void {
    describe('Customer Creation', function (): void {
        it('can create a customer', function (): void {
            $customer = Customer::create([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            expect($customer)->toBeInstanceOf(Customer::class)
                ->and($customer->first_name)->toBe('John')
                ->and($customer->last_name)->toBe('Doe');
        });

        it('generates full name', function (): void {
            $customer = Customer::create([
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            expect($customer->full_name)->toBe('Jane Smith');
        });
    });

    describe('Customer Status', function (): void {
        it('reports active and suspended flags', function (): void {
            $active = Customer::create([
                'first_name' => 'Active',
                'last_name' => 'User',
                'email' => 'active-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            expect($active->isActive())->toBeTrue()
                ->and($active->isSuspended())->toBeFalse();

            $suspended = createCustomerModelTestCustomer([
                'first_name' => 'Suspended',
                'last_name' => 'User',
                'email' => 'suspended-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Suspended,
            ]);

            expect($suspended->isSuspended())->toBeTrue()
                ->and($suspended->isActive())->toBeFalse();
        });
    });

    describe('Customer Marketing', function (): void {
        it('opts in and out of marketing', function (): void {
            $customer = Customer::create([
                'first_name' => 'Marketer',
                'last_name' => 'Test',
                'email' => 'marketer-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => false,
            ]);

            $customer->optInMarketing();

            expect($customer->accepts_marketing)->toBeTrue();

            $customer->optOutMarketing();

            expect($customer->accepts_marketing)->toBeFalse();
        });
    });

    describe('Customer Scopes', function (): void {
        it('can filter active customers', function (): void {
            Customer::create(['first_name' => 'Active', 'last_name' => 'One', 'email' => 'a1-' . uniqid() . '@test.com', 'status' => CustomerStatus::Active]);
            createCustomerModelTestCustomer(['first_name' => 'Inactive', 'last_name' => 'Two', 'email' => 'i2-' . uniqid() . '@test.com', 'status' => CustomerStatus::Suspended]);

            expect(Customer::active()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter customers by segment membership', function (): void {
            $segment = Segment::create([
                'name' => 'Scope Segment ' . uniqid(),
                'slug' => 'scope-segment-' . uniqid(),
                'is_automatic' => false,
                'is_active' => true,
            ]);

            $inSegment = Customer::create([
                'first_name' => 'In',
                'last_name' => 'Segment',
                'email' => 'in-segment-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $outsideSegment = Customer::create([
                'first_name' => 'Out',
                'last_name' => 'Segment',
                'email' => 'out-segment-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $segment->addCustomer($inSegment);

            $matchedIds = Customer::query()->inSegment($segment)->pluck('id')->all();

            expect($matchedIds)
                ->toContain($inSegment->id)
                ->not->toContain($outsideSegment->id);
        });
    });
});
