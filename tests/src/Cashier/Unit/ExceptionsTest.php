<?php

declare(strict_types=1);

use AIArmada\Cashier\Exceptions\CashierException;
use AIArmada\Cashier\Exceptions\CustomerNotFoundException;
use AIArmada\Cashier\Exceptions\GatewayNotFoundException;
use AIArmada\Cashier\Exceptions\InvalidGatewayException;
use AIArmada\Cashier\Exceptions\PaymentActionRequired;
use AIArmada\Cashier\Exceptions\PaymentFailedException;
use AIArmada\Cashier\Exceptions\SubscriptionNotFoundException;
use AIArmada\Cashier\Exceptions\SubscriptionUpdateFailure;
use AIArmada\Cashier\Exceptions\WebhookVerificationException;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('Exceptions', function () {
    describe('CashierException', function () {
        it('is the base exception class', function () {
            $exception = new CashierException('Test message');

            expect($exception)->toBeInstanceOf(Exception::class)
                ->and($exception->getMessage())->toBe('Test message');
        });

        it('can set and get gateway', function () {
            $exception = new CashierException('Test');
            $exception->setGateway('stripe');

            expect($exception->gateway())->toBe('stripe');
        });
    });

    describe('GatewayNotFoundException', function () {
        it('can be created with gateway name', function () {
            $exception = GatewayNotFoundException::forGateway('unknown');

            expect($exception)->toBeInstanceOf(GatewayNotFoundException::class)
                ->and($exception->getMessage())->toContain('unknown');
        });
    });

    describe('InvalidGatewayException', function () {
        it('can be created for invalid gateway', function () {
            $exception = InvalidGatewayException::create('stripe');

            expect($exception)->toBeInstanceOf(InvalidGatewayException::class)
                ->and($exception->getMessage())->toContain('stripe');
        });

        it('can be created for missing config', function () {
            $exception = InvalidGatewayException::missingConfig('stripe', 'secret');

            expect($exception->getMessage())->toContain('stripe')
                ->and($exception->getMessage())->toContain('secret');
        });
    });

    describe('PaymentFailedException', function () {
        it('can be created with details', function () {
            $exception = PaymentFailedException::create('stripe', 'Payment declined', [
                'payment_id' => 'pi_xxx',
                'error_code' => 'card_declined',
            ]);

            expect($exception)->toBeInstanceOf(PaymentFailedException::class)
                ->and($exception->gateway())->toBe('stripe')
                ->and($exception->paymentId())->toBe('pi_xxx')
                ->and($exception->errorCode())->toBe('card_declined');
        });
    });

    describe('PaymentActionRequired', function () {
        it('can be created with action details', function () {
            $exception = PaymentActionRequired::create(
                'stripe',
                'pi_xxx',
                'secret_xxx',
                'https://example.com/action'
            );

            expect($exception)->toBeInstanceOf(PaymentActionRequired::class)
                ->and($exception->gateway())->toBe('stripe')
                ->and($exception->paymentId())->toBe('pi_xxx')
                ->and($exception->clientSecret())->toBe('secret_xxx')
                ->and($exception->actionUrl())->toBe('https://example.com/action');
        });
    });

    describe('CustomerNotFoundException', function () {
        it('can be created with customer identifier', function () {
            $exception = CustomerNotFoundException::create('stripe', 'cus_xxx');

            expect($exception)->toBeInstanceOf(CustomerNotFoundException::class)
                ->and($exception->gateway())->toBe('stripe')
                ->and($exception->getMessage())->toContain('cus_xxx');
        });

        it('can be created for not created customer', function () {
            $exception = CustomerNotFoundException::notCreated('chip');

            expect($exception->gateway())->toBe('chip')
                ->and($exception->getMessage())->toContain('not been created');
        });
    });

    describe('SubscriptionNotFoundException', function () {
        it('can be created with subscription type', function () {
            $exception = SubscriptionNotFoundException::create('premium');

            expect($exception)->toBeInstanceOf(SubscriptionNotFoundException::class)
                ->and($exception->getMessage())->toContain('premium');
        });

        it('can be created for gateway subscription', function () {
            $exception = SubscriptionNotFoundException::onGateway('stripe', 'sub_xxx');

            expect($exception->gateway())->toBe('stripe')
                ->and($exception->getMessage())->toContain('sub_xxx');
        });
    });

    describe('SubscriptionUpdateFailure', function () {
        it('can be created for incomplete subscription', function () {
            $exception = SubscriptionUpdateFailure::incompleteSubscription('default');

            expect($exception)->toBeInstanceOf(SubscriptionUpdateFailure::class)
                ->and($exception->getMessage())->toContain('incomplete');
        });

        it('can be created for canceled subscription', function () {
            $exception = SubscriptionUpdateFailure::subscriptionCanceled();

            expect($exception->getMessage())->toContain('canceled');
        });

        it('can be created for duplicate subscription', function () {
            $exception = SubscriptionUpdateFailure::duplicateSubscription('premium');

            expect($exception->getMessage())->toContain('premium')
                ->and($exception->getMessage())->toContain('already exists');
        });
    });

    describe('WebhookVerificationException', function () {
        it('can be created for invalid signature', function () {
            $exception = WebhookVerificationException::invalidSignature('stripe');

            expect($exception)->toBeInstanceOf(WebhookVerificationException::class)
                ->and($exception->gateway())->toBe('stripe')
                ->and($exception->getMessage())->toContain('signature');
        });

        it('can be created for missing secret', function () {
            $exception = WebhookVerificationException::missingSecret('chip');

            expect($exception->gateway())->toBe('chip')
                ->and($exception->getMessage())->toContain('secret');
        });
    });
});
