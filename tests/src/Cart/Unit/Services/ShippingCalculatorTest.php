<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Services\ShippingCalculator;
use AIArmada\Cart\Storage\CacheStorage;
use Akaunting\Money\Money;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
    $storage = new CacheStorage(Cache::store(), 'shipping_test', 3600);
    $this->cart = new Cart($storage, 'test-user', null, 'default');
});

describe('ShippingCalculator', function (): void {
    describe('flat rate shipping', function (): void {
        it('calculates flat rate shipping', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1); // 5000 cents = $50

            $calculator = ShippingCalculator::create()
                ->flatRate(800); // 800 cents = $8

            $shipping = $calculator->calculate($this->cart);

            expect($shipping->getAmount())->toBe(800);
        });

        it('returns Money instance', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1);

            $calculator = ShippingCalculator::create()
                ->flatRate(800);

            $shipping = $calculator->calculate($this->cart);

            expect($shipping)->toBeInstanceOf(Money::class);
        });

        it('uses configured currency', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1);

            $calculator = ShippingCalculator::create()
                ->flatRate(800)
                ->currency('USD');

            $shipping = $calculator->calculate($this->cart);

            expect($shipping->getCurrency()->getCurrency())->toBe('USD');
        });
    });

    describe('free shipping threshold', function (): void {
        it('provides free shipping above threshold', function (): void {
            $this->cart->add('product-1', 'Test Product', 15000, 1); // 15000 cents = $150, above 10000 threshold

            $calculator = ShippingCalculator::create()
                ->flatRate(800)
                ->freeAbove(10000); // 10000 cents = $100

            $shipping = $calculator->calculate($this->cart);

            expect($shipping->getAmount())->toBe(0);
        });

        it('charges shipping below threshold', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1); // 5000 cents = $50, below 10000 threshold

            $calculator = ShippingCalculator::create()
                ->flatRate(800)
                ->freeAbove(10000);

            $shipping = $calculator->calculate($this->cart);

            expect($shipping->getAmount())->toBe(800);
        });

        it('provides free shipping at exact threshold', function (): void {
            $this->cart->add('product-1', 'Test Product', 10000, 1); // Exactly 10000 cents

            $calculator = ShippingCalculator::create()
                ->flatRate(800)
                ->freeAbove(10000);

            $shipping = $calculator->calculate($this->cart);

            expect($shipping->getAmount())->toBe(0);
        });
    });

    describe('zone based shipping', function (): void {
        it('calculates different rates per zone', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1);

            $calculator = ShippingCalculator::create()
                ->flatRate(800)           // 800 cents = $8
                ->zoneRate('MY-EAST', 1500)  // 1500 cents = $15
                ->zoneRate('SG', 2500);      // 2500 cents = $25

            expect($calculator->calculate($this->cart)->getAmount())->toBe(800);
            expect($calculator->calculate($this->cart, 'MY-EAST')->getAmount())->toBe(1500);
            expect($calculator->calculate($this->cart, 'SG')->getAmount())->toBe(2500);
        });

        it('uses default zone when not specified', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1);

            $calculator = ShippingCalculator::create()
                ->zoneRate('MY-PENINSULA', 800)
                ->zoneRate('MY-EAST', 1500)
                ->defaultZone('MY-PENINSULA');

            expect($calculator->calculate($this->cart)->getAmount())->toBe(800);
        });
    });

    describe('tiered shipping', function (): void {
        it('applies correct tier based on subtotal', function (): void {
            $calculator = ShippingCalculator::create()
                ->tier(0, 5000, 1500)      // 0-$50: $15 shipping
                ->tier(5000, 10000, 1000)  // $50-$100: $10 shipping
                ->tier(10000, null, 0);    // $100+: free

            // Cart with 3000 cents ($30)
            $this->cart->add('product-1', 'Test Product', 3000, 1);
            expect($calculator->calculate($this->cart)->getAmount())->toBe(1500);

            // Reset and add 7500 cents ($75)
            $this->cart->clear();
            $this->cart->add('product-2', 'Test Product 2', 7500, 1);
            expect($calculator->calculate($this->cart)->getAmount())->toBe(1000);

            // Reset and add 15000 cents ($150)
            $this->cart->clear();
            $this->cart->add('product-3', 'Test Product 3', 15000, 1);
            expect($calculator->calculate($this->cart)->getAmount())->toBe(0);
        });
    });

    describe('minimum and maximum caps', function (): void {
        it('applies minimum shipping charge', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1);

            $calculator = ShippingCalculator::create()
                ->flatRate(100)   // 100 cents = $1 (low)
                ->minimum(500);   // 500 cents = $5 minimum

            $shipping = $calculator->calculate($this->cart);

            expect($shipping->getAmount())->toBe(500);
        });

        it('applies maximum shipping cap', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1);

            $calculator = ShippingCalculator::create()
                ->flatRate(25000)  // 25000 cents = $250 (high)
                ->maximum(5000);   // 5000 cents = $50 cap

            $shipping = $calculator->calculate($this->cart);

            expect($shipping->getAmount())->toBe(5000);
        });
    });

    describe('apply to cart', function (): void {
        it('applies shipping condition to cart', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1);

            $calculator = ShippingCalculator::create()
                ->flatRate(800)
                ->named('Express Shipping');

            $condition = $calculator->applyToCart($this->cart);

            expect($condition->getName())->toBe('Express Shipping');
            expect($condition->getType())->toBe('shipping');
            expect((int) $condition->getValue())->toBe(800);
            expect($this->cart->getConditions())->toHaveCount(1);
        });

        it('uses shipping phase for condition order', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1);

            $calculator = ShippingCalculator::create()
                ->flatRate(800);

            $condition = $calculator->applyToCart($this->cart);

            expect($condition->getOrder())->toBe(ConditionPhase::SHIPPING->order());
        });
    });

    describe('create condition directly', function (): void {
        it('creates shipping condition without calculation', function (): void {
            $calculator = ShippingCalculator::create()
                ->named('Manual Shipping');

            $condition = $calculator->createCondition(1200, 'express'); // 1200 cents = $12

            expect($condition->getName())->toBe('Manual Shipping');
            expect((int) $condition->getValue())->toBe(1200);
            expect($condition->getAttributes()['method'])->toBe('express');
        });
    });

    describe('preset configurations', function (): void {
        it('creates Malaysia defaults', function (): void {
            $calculator = ShippingCalculator::malaysiaDefaults();

            $this->cart->add('product-1', 'Test Product', 5000, 1); // 5000 sen = RM 50

            expect($calculator->calculate($this->cart)->getAmount())->toBe(800); // RM 8 = 800 sen
            expect($calculator->calculate($this->cart, 'MY-EAST')->getAmount())->toBe(1500); // RM 15
            expect($calculator->calculate($this->cart, 'SG')->getAmount())->toBe(2500); // RM 25
            expect($calculator->calculate($this->cart, 'INTERNATIONAL')->getAmount())->toBe(5000); // RM 50
        });

        it('Malaysia defaults provides free shipping above threshold', function (): void {
            $calculator = ShippingCalculator::malaysiaDefaults();

            $this->cart->add('product-1', 'Expensive Product', 20000, 1); // 20000 sen = RM 200, above RM 150 threshold

            expect($calculator->calculate($this->cart)->getAmount())->toBe(0);
        });

        it('creates tiered defaults', function (): void {
            $calculator = ShippingCalculator::tieredDefaults();

            // Under $50 (under 5000 cents)
            $this->cart->add('product-1', 'Cheap Product', 3000, 1); // 3000 cents = $30
            expect($calculator->calculate($this->cart)->getAmount())->toBe(1500); // $15 shipping

            // $50-100 range (5000-10000 cents)
            $this->cart->clear();
            $this->cart->add('product-2', 'Medium Product', 7000, 1); // 7000 cents = $70
            expect($calculator->calculate($this->cart)->getAmount())->toBe(1000); // $10 shipping

            // $100-200 range (10000-20000 cents)
            $this->cart->clear();
            $this->cart->add('product-3', 'Nice Product', 15000, 1); // 15000 cents = $150
            expect($calculator->calculate($this->cart)->getAmount())->toBe(500); // $5 shipping

            // $200+ range (20000+ cents)
            $this->cart->clear();
            $this->cart->add('product-4', 'Expensive Product', 25000, 1); // 25000 cents = $250
            expect($calculator->calculate($this->cart)->getAmount())->toBe(0); // Free
        });
    });

    describe('fluent configuration', function (): void {
        it('supports chained configuration', function (): void {
            $calculator = ShippingCalculator::create()
                ->flatRate(800)
                ->freeAbove(15000)
                ->weightRate(100, perGrams: 1000) // 100 cents = $1 per kg
                ->minimum(500)
                ->maximum(5000)
                ->zoneRate('SG', 2500)
                ->named('Custom Shipping')
                ->currency('USD');

            expect($calculator)->toBeInstanceOf(ShippingCalculator::class);
        });
    });

    describe('edge cases', function (): void {
        it('handles empty cart with flat rate', function (): void {
            $calculator = ShippingCalculator::create()
                ->flatRate(800);

            $shipping = $calculator->calculate($this->cart);

            expect($shipping->getAmount())->toBe(800);
        });
    });
});
