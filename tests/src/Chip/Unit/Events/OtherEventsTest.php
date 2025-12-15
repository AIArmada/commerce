<?php

declare(strict_types=1);

use AIArmada\Chip\Data\BillingTemplateClientData;
use AIArmada\Chip\Data\PayoutData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Events\BillingCancelled;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\WebhookReceived;

describe('PaymentRefunded event', function () {
    it('can create from payload', function () {
        $payload = [
            'id' => 'purch_refund_123',
            'status' => 'refunded',
            'type' => 'purchase',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => [
                'total' => 10000,
                'currency' => 'MYR',
                'products' => [['name' => 'Test', 'price' => 10000, 'quantity' => 1]],
            ],
            'reference' => 'REF-REFUND-123',
            'is_test' => true,
        ];

        $event = PaymentRefunded::fromPayload($payload);

        expect($event)->toBeInstanceOf(PaymentRefunded::class)
            ->and($event->eventType())->toBe(WebhookEventType::PaymentRefunded)
            ->and($event->getAmount())->toBe(10000)
            ->and($event->getCurrency())->toBe('MYR')
            ->and($event->getPurchaseId())->toBe('purch_refund_123')
            ->and($event->getReference())->toBe('REF-REFUND-123')
            ->and($event->isTest())->toBeTrue();
    });

    it('returns default values when purchase is null', function () {
        $event = new PaymentRefunded(null, []);

        expect($event->getAmount())->toBe(0)
            ->and($event->getCurrency())->toBe('MYR')
            ->and($event->getPurchaseId())->toBeNull()
            ->and($event->getReference())->toBeNull()
            ->and($event->isTest())->toBeTrue();
    });

    it('returns false for isTest when explicitly set', function () {
        $payload = [
            'id' => 'purch_live',
            'status' => 'refunded',
            'type' => 'purchase',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => [
                'total' => 5000,
                'currency' => 'USD',
                'products' => [['name' => 'Test', 'price' => 5000, 'quantity' => 1]],
            ],
            'is_test' => false,
        ];

        $event = PaymentRefunded::fromPayload($payload);

        expect($event->isTest())->toBeFalse()
            ->and($event->getCurrency())->toBe('USD');
    });
});

describe('BillingCancelled event', function () {
    function createBillingPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => 'btc_test123',
            'type' => 'billing_template_client',
            'created_on' => time(),
            'updated_on' => time(),
            'status' => 'subscription_paused',
            'billing_template_id' => 'bt_template_123',
            'client_id' => 'client_abc',
            'recurring_token' => 'token_xyz',
            'next_billing_on' => time() + 86400,
            'last_billing_on' => time() - 86400,
            'company_id' => 'company_123',
            'is_test' => true,
            'metadata' => ['plan' => 'premium'],
        ], $overrides);
    }

    it('can create from payload', function () {
        $payload = createBillingPayload();

        $event = BillingCancelled::fromPayload($payload);

        expect($event)->toBeInstanceOf(BillingCancelled::class)
            ->and($event->eventType())->toBe(WebhookEventType::BillingTemplateClientSubscriptionBillingCancelled)
            ->and($event->getBillingTemplateClientId())->toBe('btc_test123')
            ->and($event->getBillingTemplateId())->toBe('bt_template_123')
            ->and($event->getClientId())->toBe('client_abc')
            ->and($event->isTest())->toBeTrue();
    });

    it('correctly checks isTest for live event', function () {
        $payload = createBillingPayload(['is_test' => false]);

        $event = BillingCancelled::fromPayload($payload);

        expect($event->isTest())->toBeFalse();
    });
});

describe('WebhookReceived event', function () {
    it('can create from purchase payload', function () {
        $payload = [
            'id' => 'purch_123',
            'type' => 'purchase',
            'event_type' => 'purchase.paid',
            'status' => 'paid',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => [
                'total' => 10000,
                'currency' => 'MYR',
                'products' => [['name' => 'Test', 'price' => 10000, 'quantity' => 1]],
            ],
            'is_test' => true,
        ];

        $event = WebhookReceived::fromPayload($payload);

        expect($event)->toBeInstanceOf(WebhookReceived::class)
            ->and($event->eventType)->toBe('purchase.paid')
            ->and($event->purchase)->toBeInstanceOf(PurchaseData::class)
            ->and($event->payout)->toBeNull()
            ->and($event->billingTemplateClient)->toBeNull();
    });

    it('can create from payout payload', function () {
        $payload = [
            'id' => 'payout_123',
            'type' => 'payout',
            'event_type' => 'payout.success',
            'status' => 'success',
            'created_on' => time(),
            'updated_on' => time(),
            'amount' => 50000,
            'currency' => 'MYR',
            'is_test' => true,
        ];

        $event = WebhookReceived::fromPayload($payload);

        expect($event->payout)->toBeInstanceOf(PayoutData::class)
            ->and($event->purchase)->toBeNull()
            ->and($event->billingTemplateClient)->toBeNull();
    });

    it('can create from billing template client payload', function () {
        $payload = [
            'id' => 'btc_123',
            'type' => 'billing_template_client',
            'event_type' => 'billing_template_client.subscription_billing_cancelled',
            'status' => 'subscription_paused',
            'created_on' => time(),
            'updated_on' => time(),
            'billing_template_id' => 'bt_123',
            'client_id' => 'client_123',
            'is_test' => true,
        ];

        $event = WebhookReceived::fromPayload($payload);

        expect($event->billingTemplateClient)->toBeInstanceOf(BillingTemplateClientData::class)
            ->and($event->purchase)->toBeNull()
            ->and($event->payout)->toBeNull();
    });

    it('returns event type enum when valid', function () {
        $payload = ['event_type' => 'purchase.paid', 'is_test' => true];
        $event = new WebhookReceived('purchase.paid', $payload);

        expect($event->getEventTypeEnum())->toBe(WebhookEventType::PurchasePaid);
    });

    it('returns null for unknown event type', function () {
        $payload = ['event_type' => 'unknown.event', 'is_test' => true];
        $event = new WebhookReceived('unknown.event', $payload);

        expect($event->getEventTypeEnum())->toBeNull();
    });

    it('correctly identifies purchase lifecycle events', function () {
        $events = [
            'purchase.created' => 'isCreated',
            'purchase.paid' => 'isPaid',
            'purchase.payment_failure' => 'isPaymentFailure',
            'purchase.cancelled' => 'isCancelled',
        ];

        foreach ($events as $eventType => $method) {
            $event = new WebhookReceived($eventType, ['is_test' => true]);
            expect($event->$method())->toBeTrue("Failed for {$method}");
        }
    });

    it('correctly identifies pending events', function () {
        $events = [
            'purchase.pending_execute' => 'isPendingExecute',
            'purchase.pending_charge' => 'isPendingCharge',
            'purchase.pending_capture' => 'isPendingCapture',
            'purchase.pending_release' => 'isPendingRelease',
            'purchase.pending_refund' => 'isPendingRefund',
            'purchase.pending_recurring_token_delete' => 'isPendingRecurringTokenDelete',
        ];

        foreach ($events as $eventType => $method) {
            $event = new WebhookReceived($eventType, ['is_test' => true]);
            expect($event->$method())->toBeTrue("Failed for {$method}");
        }
    });

    it('correctly identifies authorization/capture events', function () {
        $events = [
            'purchase.hold' => 'isHold',
            'purchase.captured' => 'isCaptured',
            'purchase.released' => 'isReleased',
            'purchase.preauthorized' => 'isPreauthorized',
        ];

        foreach ($events as $eventType => $method) {
            $event = new WebhookReceived($eventType, ['is_test' => true]);
            expect($event->$method())->toBeTrue("Failed for {$method}");
        }
    });

    it('correctly identifies recurring and subscription events', function () {
        $event1 = new WebhookReceived('purchase.recurring_token_deleted', ['is_test' => true]);
        expect($event1->isRecurringTokenDeleted())->toBeTrue();

        $event2 = new WebhookReceived('purchase.subscription_charge_failure', ['is_test' => true]);
        expect($event2->isSubscriptionChargeFailure())->toBeTrue();
    });

    it('correctly identifies refund and billing events', function () {
        $event1 = new WebhookReceived('payment.refunded', ['is_test' => true]);
        expect($event1->isRefunded())->toBeTrue();

        $event2 = new WebhookReceived('billing_template_client.subscription_billing_cancelled', ['is_test' => true]);
        expect($event2->isBillingCancelled())->toBeTrue();
    });

    it('correctly identifies payout events', function () {
        $events = [
            'payout.pending' => 'isPayoutPending',
            'payout.failed' => 'isPayoutFailed',
            'payout.success' => 'isPayoutSuccess',
        ];

        foreach ($events as $eventType => $method) {
            $event = new WebhookReceived($eventType, ['is_test' => true]);
            expect($event->$method())->toBeTrue("Failed for {$method}");
        }
    });

    it('correctly identifies event categories', function () {
        expect((new WebhookReceived('purchase.paid', []))->isPurchaseEvent())->toBeTrue();
        expect((new WebhookReceived('payout.success', []))->isPayoutEvent())->toBeTrue();
        expect((new WebhookReceived('billing_template_client.cancelled', []))->isBillingEvent())->toBeTrue();
        expect((new WebhookReceived('payment.refunded', []))->isPaymentEvent())->toBeTrue();
        expect((new WebhookReceived('purchase.pending_charge', []))->isPendingEvent())->toBeTrue();
    });

    it('correctly identifies success events', function () {
        $successEvents = [
            'purchase.paid',
            'purchase.captured',
            'purchase.released',
            'purchase.preauthorized',
            'payout.success',
        ];

        foreach ($successEvents as $eventType) {
            $event = new WebhookReceived($eventType, ['is_test' => true]);
            expect($event->isSuccessEvent())->toBeTrue("Failed for {$eventType}");
        }
    });

    it('correctly identifies failure events', function () {
        $failureEvents = [
            'purchase.payment_failure',
            'purchase.subscription_charge_failure',
            'payout.failed',
        ];

        foreach ($failureEvents as $eventType) {
            $event = new WebhookReceived($eventType, ['is_test' => true]);
            expect($event->isFailureEvent())->toBeTrue("Failed for {$eventType}");
        }
    });

    it('provides correct data accessors', function () {
        $payload = [
            'event_type' => 'purchase.paid',
            'id' => 'purch_xyz',
            'reference' => 'REF-123',
            'client_id' => 'client_abc',
            'amount' => 15000,
            'currency' => 'USD',
            'is_test' => false,
        ];

        $event = new WebhookReceived('purchase.paid', $payload);

        expect($event->getReference())->toBe('REF-123')
            ->and($event->getPurchaseId())->toBe('purch_xyz')
            ->and($event->getClientId())->toBe('client_abc')
            ->and($event->getAmount())->toBe(15000)
            ->and($event->getCurrency())->toBe('USD')
            ->and($event->isTest())->toBeFalse();
    });

    it('returns default values for missing data', function () {
        $event = new WebhookReceived('unknown', []);

        expect($event->getReference())->toBeNull()
            ->and($event->getPurchaseId())->toBeNull()
            ->and($event->getClientId())->toBeNull()
            ->and($event->getAmount())->toBe(0)
            ->and($event->getCurrency())->toBe('MYR')
            ->and($event->isTest())->toBeTrue();
    });

    it('gets amount from nested purchase data', function () {
        $payload = [
            'purchase' => [
                'total' => 25000,
                'currency' => 'SGD',
            ],
        ];

        $event = new WebhookReceived('purchase.paid', $payload);

        expect($event->getAmount())->toBe(25000)
            ->and($event->getCurrency())->toBe('SGD');
    });
});
