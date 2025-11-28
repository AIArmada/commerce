<?php

declare(strict_types=1);

use AIArmada\Cashier\Models\Subscription;
use AIArmada\Cashier\Models\SubscriptionItem;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('SubscriptionItem Model', function () {
    beforeEach(function () {
        $this->user = $this->createUser();
        $this->subscription = $this->createSubscription($this->user);
    });

    describe('creation', function () {
        it('can create a subscription item', function () {
            $item = $this->createSubscriptionItem($this->subscription);

            expect($item)->toBeInstanceOf(SubscriptionItem::class)
                ->and($item->gateway_price)->toBe('price_xxx')
                ->and($item->quantity)->toBe(1);
        });

        it('belongs to a subscription', function () {
            $item = $this->createSubscriptionItem($this->subscription);

            expect($item->subscription)->toBeInstanceOf(Subscription::class)
                ->and($item->subscription->id)->toBe($this->subscription->id);
        });
    });

    describe('gateway information', function () {
        it('can get gateway name from parent subscription', function () {
            $item = $this->createSubscriptionItem($this->subscription);

            expect($item->gateway())->toBe('stripe');
        });

        it('can get gateway item id', function () {
            $item = $this->createSubscriptionItem($this->subscription, [
                'gateway_id' => 'si_test123',
            ]);

            expect($item->gatewayId())->toBe('si_test123');
        });

        it('can get price id', function () {
            $item = $this->createSubscriptionItem($this->subscription, [
                'gateway_price' => 'price_monthly',
            ]);

            expect($item->priceId())->toBe('price_monthly');
        });

        it('can get product id', function () {
            $item = $this->createSubscriptionItem($this->subscription, [
                'gateway_product' => 'prod_test123',
            ]);

            expect($item->productId())->toBe('prod_test123');
        });
    });

    describe('quantity management', function () {
        it('can increment quantity', function () {
            $item = $this->createSubscriptionItem($this->subscription, [
                'quantity' => 5,
            ]);

            $item->incrementQuantity(3);
            $item->refresh();

            expect($item->quantity)->toBe(8);
        });

        it('can decrement quantity', function () {
            $item = $this->createSubscriptionItem($this->subscription, [
                'quantity' => 5,
            ]);

            $item->decrementQuantity(2);
            $item->refresh();

            expect($item->quantity)->toBe(3);
        });

        it('does not decrement below 1', function () {
            $item = $this->createSubscriptionItem($this->subscription, [
                'quantity' => 2,
            ]);

            $item->decrementQuantity(5);
            $item->refresh();

            expect($item->quantity)->toBe(1);
        });

        it('can update quantity directly', function () {
            $item = $this->createSubscriptionItem($this->subscription, [
                'quantity' => 5,
            ]);

            $item->updateQuantity(10);
            $item->refresh();

            expect($item->quantity)->toBe(10);
        });
    });

    describe('swap', function () {
        it('can swap to a new price', function () {
            $item = $this->createSubscriptionItem($this->subscription, [
                'gateway_price' => 'price_monthly',
            ]);

            $item->swap('price_yearly');
            $item->refresh();

            expect($item->gateway_price)->toBe('price_yearly');
        });

        it('can swap to a new price with product', function () {
            $item = $this->createSubscriptionItem($this->subscription, [
                'gateway_price' => 'price_monthly',
                'gateway_product' => 'prod_basic',
            ]);

            $item->swap('price_yearly', ['product' => 'prod_premium']);
            $item->refresh();

            expect($item->gateway_price)->toBe('price_yearly')
                ->and($item->gateway_product)->toBe('prod_premium');
        });
    });

    describe('casts', function () {
        it('casts quantity to integer', function () {
            $item = $this->createSubscriptionItem($this->subscription, [
                'quantity' => '5',
            ]);

            expect($item->quantity)->toBeInt();
        });

        it('casts unit_amount to integer', function () {
            $item = $this->createSubscriptionItem($this->subscription, [
                'unit_amount' => '1000',
            ]);

            expect($item->unit_amount)->toBeInt();
        });
    });
});
