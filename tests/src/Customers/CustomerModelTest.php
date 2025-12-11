<?php

declare(strict_types=1);

use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Enums\CustomerStatus;

describe('Customer Model', function () {
    describe('Customer Creation', function () {
        it('can create a customer', function () {
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

        it('generates full name', function () {
            $customer = Customer::create([
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            expect($customer->full_name)->toBe('Jane Smith');
        });
    });

    describe('Customer Status', function () {
        it('can check if customer is active', function () {
            $active = Customer::create([
                'first_name' => 'Active',
                'last_name' => 'User',
                'email' => 'active-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            expect($active->isActive())->toBeTrue();
        });

        it('can check if customer is suspended', function () {
            $suspended = Customer::create([
                'first_name' => 'Suspended',
                'last_name' => 'User',
                'email' => 'suspended-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Suspended,
            ]);

            expect($suspended->isSuspended())->toBeTrue();
        });
    });

    describe('Customer Marketing', function () {
        it('can opt in to marketing', function () {
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

        it('can opt out of marketing', function () {
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

    describe('Customer Wallet', function () {
        it('can add credit to wallet', function () {
            $customer = Customer::create([
                'first_name' => 'Wallet',
                'last_name' => 'Test',
                'email' => 'wallet-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'wallet_balance' => 0,
            ]);

            $customer->addCredit(1000);

            expect($customer->wallet_balance)->toBe(1000);
        });

        it('can deduct credit from wallet', function () {
            $customer = Customer::create([
                'first_name' => 'Wallet',
                'last_name' => 'Test',
                'email' => 'wallet2-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'wallet_balance' => 5000,
            ]);

            $customer->deductCredit(2000);

            expect($customer->wallet_balance)->toBe(3000);
        });

        it('can check wallet balance', function () {
            $customer = Customer::create([
                'first_name' => 'Wallet',
                'last_name' => 'Test',
                'email' => 'wallet3-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'wallet_balance' => 1000,
            ]);

            expect($customer->hasWalletBalance(500))->toBeTrue()
                ->and($customer->hasWalletBalance(2000))->toBeFalse();
        });
    });

    describe('Customer Order Statistics', function () {
        it('can record an order', function () {
            $customer = Customer::create([
                'first_name' => 'Order',
                'last_name' => 'Test',
                'email' => 'order-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'total_orders' => 0,
                'lifetime_value' => 0,
            ]);

            $customer->recordOrder(5000);

            expect($customer->total_orders)->toBe(1)
                ->and($customer->lifetime_value)->toBe(5000);
        });

        it('can accumulate multiple orders', function () {
            $customer = Customer::create([
                'first_name' => 'Order',
                'last_name' => 'Test',
                'email' => 'order2-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'total_orders' => 0,
                'lifetime_value' => 0,
            ]);

            $customer->recordOrder(2000);
            $customer->recordOrder(3000);
            $customer->recordOrder(5000);

            expect($customer->total_orders)->toBe(3)
                ->and($customer->lifetime_value)->toBe(10000);
        });

        it('can calculate average order value', function () {
            $customer = Customer::create([
                'first_name' => 'Order',
                'last_name' => 'Test',
                'email' => 'order3-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'total_orders' => 4,
                'lifetime_value' => 10000,
            ]);

            expect($customer->getAverageOrderValue())->toBe(2500);
        });
    });

    describe('Customer Scopes', function () {
        it('can filter active customers', function () {
            Customer::create(['first_name' => 'Active', 'last_name' => 'One', 'email' => 'a1-' . uniqid() . '@test.com', 'status' => CustomerStatus::Active]);
            Customer::create(['first_name' => 'Inactive', 'last_name' => 'Two', 'email' => 'i2-' . uniqid() . '@test.com', 'status' => CustomerStatus::Suspended]);

            expect(Customer::active()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter marketing opted-in customers', function () {
            Customer::create(['first_name' => 'OptedIn', 'last_name' => 'User', 'email' => 'optin-' . uniqid() . '@test.com', 'status' => CustomerStatus::Active, 'accepts_marketing' => true]);
            Customer::create(['first_name' => 'OptedOut', 'last_name' => 'User', 'email' => 'optout-' . uniqid() . '@test.com', 'status' => CustomerStatus::Active, 'accepts_marketing' => false]);

            expect(Customer::where('accepts_marketing', true)->count())->toBeGreaterThanOrEqual(1);
        });
    });

    describe('Customer Soft Deletes', function () {
        it('can soft delete a customer', function () {
            $customer = Customer::create([
                'first_name' => 'ToDelete',
                'last_name' => 'User',
                'email' => 'delete-' . uniqid() . '@test.com',
                'status' => CustomerStatus::Active,
            ]);

            $id = $customer->id;
            $customer->delete();

            expect(Customer::find($id))->toBeNull()
                ->and(Customer::withTrashed()->find($id))->not->toBeNull();
        });
    });
});
