<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CheckoutBuilderContract;
use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Contracts\CustomerContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\InvoiceContract;
use AIArmada\Cashier\Contracts\InvoiceLineItemContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Contracts\PaymentMethodContract;
use AIArmada\Cashier\Contracts\SubscriptionBuilderContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Contracts\SubscriptionItemContract;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('Contracts', function () {
    describe('GatewayContract', function () {
        it('exists and is an interface', function () {
            expect(interface_exists(GatewayContract::class))->toBeTrue();
        });

        it('defines required methods', function () {
            $reflection = new ReflectionClass(GatewayContract::class);
            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('name')
                ->and($methods)->toContain('displayName')
                ->and($methods)->toContain('isAvailable')
                ->and($methods)->toContain('currency')
                ->and($methods)->toContain('customer')
                ->and($methods)->toContain('newSubscription')
                ->and($methods)->toContain('checkout');
        });
    });

    describe('BillableContract', function () {
        it('exists and is an interface', function () {
            expect(interface_exists(BillableContract::class))->toBeTrue();
        });

        it('defines required methods', function () {
            $reflection = new ReflectionClass(BillableContract::class);
            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('gateway')
                ->and($methods)->toContain('gatewayId')
                ->and($methods)->toContain('newSubscription')
                ->and($methods)->toContain('subscription')
                ->and($methods)->toContain('subscriptions')
                ->and($methods)->toContain('subscribed')
                ->and($methods)->toContain('onTrial')
                ->and($methods)->toContain('charge')
                ->and($methods)->toContain('paymentMethods');
        });
    });

    describe('CustomerContract', function () {
        it('exists and is an interface', function () {
            expect(interface_exists(CustomerContract::class))->toBeTrue();
        });
    });

    describe('SubscriptionContract', function () {
        it('exists and is an interface', function () {
            expect(interface_exists(SubscriptionContract::class))->toBeTrue();
        });

        it('defines required methods', function () {
            $reflection = new ReflectionClass(SubscriptionContract::class);
            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('gatewayId')
                ->and($methods)->toContain('status')
                ->and($methods)->toContain('valid')
                ->and($methods)->toContain('active')
                ->and($methods)->toContain('onTrial')
                ->and($methods)->toContain('canceled')
                ->and($methods)->toContain('onGracePeriod')
                ->and($methods)->toContain('ended')
                ->and($methods)->toContain('cancel')
                ->and($methods)->toContain('cancelNow')
                ->and($methods)->toContain('resume');
        });
    });

    describe('SubscriptionBuilderContract', function () {
        it('exists and is an interface', function () {
            expect(interface_exists(SubscriptionBuilderContract::class))->toBeTrue();
        });

        it('defines fluent builder methods', function () {
            $reflection = new ReflectionClass(SubscriptionBuilderContract::class);
            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('price')
                ->and($methods)->toContain('quantity')
                ->and($methods)->toContain('trialDays')
                ->and($methods)->toContain('trialUntil')
                ->and($methods)->toContain('skipTrial')
                ->and($methods)->toContain('create');
        });
    });

    describe('SubscriptionItemContract', function () {
        it('exists and is an interface', function () {
            expect(interface_exists(SubscriptionItemContract::class))->toBeTrue();
        });

        it('defines required methods', function () {
            $reflection = new ReflectionClass(SubscriptionItemContract::class);
            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('gatewayId')
                ->and($methods)->toContain('subscription')
                ->and($methods)->toContain('quantity')
                ->and($methods)->toContain('incrementQuantity')
                ->and($methods)->toContain('decrementQuantity')
                ->and($methods)->toContain('updateQuantity')
                ->and($methods)->toContain('swap');
        });
    });

    describe('PaymentContract', function () {
        it('exists and is an interface', function () {
            expect(interface_exists(PaymentContract::class))->toBeTrue();
        });

        it('defines required methods', function () {
            $reflection = new ReflectionClass(PaymentContract::class);
            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('id')
                ->and($methods)->toContain('rawAmount')
                ->and($methods)->toContain('currency')
                ->and($methods)->toContain('status')
                ->and($methods)->toContain('isSucceeded')
                ->and($methods)->toContain('isFailed')
                ->and($methods)->toContain('requiresAction');
        });
    });

    describe('PaymentMethodContract', function () {
        it('exists and is an interface', function () {
            expect(interface_exists(PaymentMethodContract::class))->toBeTrue();
        });

        it('defines required methods', function () {
            $reflection = new ReflectionClass(PaymentMethodContract::class);
            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('id')
                ->and($methods)->toContain('type')
                ->and($methods)->toContain('delete');
        });
    });

    describe('InvoiceContract', function () {
        it('exists and is an interface', function () {
            expect(interface_exists(InvoiceContract::class))->toBeTrue();
        });

        it('defines required methods', function () {
            $reflection = new ReflectionClass(InvoiceContract::class);
            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('id')
                ->and($methods)->toContain('number')
                ->and($methods)->toContain('rawTotal')
                ->and($methods)->toContain('rawSubtotal')
                ->and($methods)->toContain('rawTax')
                ->and($methods)->toContain('currency')
                ->and($methods)->toContain('date')
                ->and($methods)->toContain('items');
        });
    });

    describe('InvoiceLineItemContract', function () {
        it('exists and is an interface', function () {
            expect(interface_exists(InvoiceLineItemContract::class))->toBeTrue();
        });

        it('defines required methods', function () {
            $reflection = new ReflectionClass(InvoiceLineItemContract::class);
            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('id')
                ->and($methods)->toContain('description')
                ->and($methods)->toContain('quantity')
                ->and($methods)->toContain('rawUnitAmount')
                ->and($methods)->toContain('rawTotal');
        });
    });

    describe('CheckoutContract', function () {
        it('exists and is an interface', function () {
            expect(interface_exists(CheckoutContract::class))->toBeTrue();
        });

        it('defines required methods', function () {
            $reflection = new ReflectionClass(CheckoutContract::class);
            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('id')
                ->and($methods)->toContain('url')
                ->and($methods)->toContain('status')
                ->and($methods)->toContain('isComplete')
                ->and($methods)->toContain('isSuccessful')
                ->and($methods)->toContain('isExpired');
        });
    });

    describe('CheckoutBuilderContract', function () {
        it('exists and is an interface', function () {
            expect(interface_exists(CheckoutBuilderContract::class))->toBeTrue();
        });

        it('defines fluent builder methods', function () {
            $reflection = new ReflectionClass(CheckoutBuilderContract::class);
            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('prices')
                ->and($methods)->toContain('successUrl')
                ->and($methods)->toContain('cancelUrl')
                ->and($methods)->toContain('metadata')
                ->and($methods)->toContain('create');
        });
    });
});
