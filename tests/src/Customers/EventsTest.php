<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Events\CustomerAddedToSegment;
use AIArmada\Customers\Events\CustomerCreated;
use AIArmada\Customers\Events\CustomerSegmentChanged;
use AIArmada\Customers\Events\CustomerUpdated;
use AIArmada\Customers\Events\WalletCreditAdded;
use AIArmada\Customers\Events\WalletCreditDeducted;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;

describe('Customer Events', function (): void {
    describe('CustomerCreated', function (): void {
        it('can be instantiated with customer', function (): void {
            $customer = Customer::create([
                'first_name' => 'Event',
                'last_name' => 'Test',
                'email' => 'event-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $event = new CustomerCreated($customer);

            expect($event->customer)->toBe($customer);
        });
    });

    describe('CustomerUpdated', function (): void {
        it('can be instantiated with customer', function (): void {
            $customer = Customer::create([
                'first_name' => 'Updated',
                'last_name' => 'Test',
                'email' => 'updated-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $event = new CustomerUpdated($customer);

            expect($event->customer)->toBe($customer);
        });
    });

    describe('CustomerAddedToSegment', function (): void {
        it('can be instantiated with customer and segment', function (): void {
            $customer = Customer::create([
                'first_name' => 'Segment',
                'last_name' => 'Event',
                'email' => 'segment-event-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $segment = Segment::create([
                'name' => 'Event Segment',
                'slug' => 'event-segment-' . uniqid(),
            ]);

            $event = new CustomerAddedToSegment($customer, $segment);

            expect($event->customer)->toBe($customer)
                ->and($event->segment)->toBe($segment);
        });
    });

    describe('CustomerSegmentChanged', function (): void {
        it('can be instantiated with customer, segment and action', function (): void {
            $customer = Customer::create([
                'first_name' => 'Changed',
                'last_name' => 'Event',
                'email' => 'changed-event-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $segment = Segment::create([
                'name' => 'Changed Segment',
                'slug' => 'changed-segment-' . uniqid(),
            ]);

            $addedEvent = new CustomerSegmentChanged($customer, $segment, 'added');
            $removedEvent = new CustomerSegmentChanged($customer, $segment, 'removed');

            expect($addedEvent->customer)->toBe($customer)
                ->and($addedEvent->segment)->toBe($segment)
                ->and($addedEvent->action)->toBe('added')
                ->and($removedEvent->action)->toBe('removed');
        });
    });

    describe('WalletCreditAdded', function (): void {
        it('can be instantiated with customer and amount', function (): void {
            $customer = Customer::create([
                'first_name' => 'Wallet',
                'last_name' => 'Added',
                'email' => 'wallet-added-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $event = new WalletCreditAdded($customer, 5000);

            expect($event->customer)->toBe($customer)
                ->and($event->amountInCents)->toBe(5000)
                ->and($event->reason)->toBeNull();
        });

        it('accepts optional reason', function (): void {
            $customer = Customer::create([
                'first_name' => 'Wallet',
                'last_name' => 'Reason',
                'email' => 'wallet-reason-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $event = new WalletCreditAdded($customer, 5000, 'Reward for loyalty');

            expect($event->reason)->toBe('Reward for loyalty');
        });
    });

    describe('WalletCreditDeducted', function (): void {
        it('can be instantiated with customer and amount', function (): void {
            $customer = Customer::create([
                'first_name' => 'Wallet',
                'last_name' => 'Deducted',
                'email' => 'wallet-deducted-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $event = new WalletCreditDeducted($customer, 2000);

            expect($event->customer)->toBe($customer)
                ->and($event->amountInCents)->toBe(2000)
                ->and($event->reason)->toBeNull();
        });

        it('accepts optional reason', function (): void {
            $customer = Customer::create([
                'first_name' => 'Wallet',
                'last_name' => 'Deduct Reason',
                'email' => 'wallet-deduct-reason-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $event = new WalletCreditDeducted($customer, 2000, 'Order payment');

            expect($event->reason)->toBe('Order payment');
        });
    });
});
