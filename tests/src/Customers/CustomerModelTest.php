<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;

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
        it('can check if customer is active', function (): void {
            $active = Customer::create([
                'first_name' => 'Active',
                'last_name' => 'User',
                'email' => 'active-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            expect($active->isActive())->toBeTrue();
        });

        it('can check if customer is suspended', function (): void {
            $suspended = Customer::create([
                'first_name' => 'Suspended',
                'last_name' => 'User',
                'email' => 'suspended-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Suspended,
            ]);

            expect($suspended->isSuspended())->toBeTrue();
        });
    });

    describe('Customer Marketing', function (): void {
        it('can opt in to marketing', function (): void {
            $customer = Customer::create([
                'first_name' => 'Marketer',
                'last_name' => 'Test',
                'email' => 'marketer-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => false,
            ]);

            $customer->optInMarketing();

            expect($customer->accepts_marketing)->toBeTrue();
        });

        it('can opt out of marketing', function (): void {
            $customer = Customer::create([
                'first_name' => 'Marketer',
                'last_name' => 'Test',
                'email' => 'marketer2-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => true,
            ]);

            $customer->optOutMarketing();

            expect($customer->accepts_marketing)->toBeFalse();
        });
    });

    describe('Customer Scopes', function (): void {
        it('can filter active customers', function (): void {
            Customer::create(['first_name' => 'Active', 'last_name' => 'One', 'email' => 'a1-' . uniqid() . '@test.com', 'status' => CustomerStatus::Active]);
            Customer::create(['first_name' => 'Inactive', 'last_name' => 'Two', 'email' => 'i2-' . uniqid() . '@test.com', 'status' => CustomerStatus::Suspended]);

            expect(Customer::active()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter marketing opted-in customers', function (): void {
            Customer::create(['first_name' => 'OptedIn', 'last_name' => 'User', 'email' => 'optin-' . uniqid() . '@test.com', 'status' => CustomerStatus::Active, 'accepts_marketing' => true]);
            Customer::create(['first_name' => 'OptedOut', 'last_name' => 'User', 'email' => 'optout-' . uniqid() . '@test.com', 'status' => CustomerStatus::Active, 'accepts_marketing' => false]);

            expect(Customer::where('accepts_marketing', true)->count())->toBeGreaterThanOrEqual(1);
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
