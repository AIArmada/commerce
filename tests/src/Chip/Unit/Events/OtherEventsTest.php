<?php

declare(strict_types=1);

use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\PayoutData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\WebhookReceived;

describe('PaymentRefunded event', function (): void {
    it('can create from payload', function (): void {
        $payload = [
            'id' => 'pay_refund_123',
            'status' => 'refunded',
            'type' => 'payment',
            'created_on' => time(),
            'updated_on' => time(),
            'payment' => [
                'amount' => 10000,
                'currency' => 'MYR',
                'net_amount' => 10000,
                'fee_amount' => 0,
                'pending_amount' => 0,
                'payment_type' => 'refund',
                'is_outgoing' => true,
            ],
            'related_to' => [
                'type' => 'purchase',
                'id' => 'purch_refund_123',
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

    it('returns default values when purchase is null', function (): void {
        $event = new PaymentRefunded(null, []);

        expect($event->getAmount())->toBe(0)
            ->and($event->getCurrency())->toBe('MYR')
            ->and($event->getPurchaseId())->toBeNull()
            ->and($event->getReference())->toBeNull()
            ->and($event->isTest())->toBeFalse();
    });

    it('returns false for isTest when explicitly set', function (): void {
        $payload = [
            'id' => 'pay_live',
            'status' => 'refunded',
            'type' => 'payment',
            'created_on' => time(),
            'updated_on' => time(),
            'payment' => [
                'amount' => 5000,
                'currency' => 'USD',
                'net_amount' => 5000,
                'fee_amount' => 0,
                'pending_amount' => 0,
                'payment_type' => 'refund',
                'is_outgoing' => true,
            ],
            'related_to' => [
                'type' => 'purchase',
                'id' => 'purch_live',
            ],
            'is_test' => false,
        ];

        $event = PaymentRefunded::fromPayload($payload);

        expect($event->isTest())->toBeFalse()
            ->and($event->getCurrency())->toBe('USD');
    });

    it('fails closed for malformed refund payloads without payment details', function (): void {
        $payload = [
            'id' => 'pay_refund_invalid',
            'status' => 'refunded',
            'type' => 'payment',
            'event_type' => 'payment.refunded',
            'created_on' => time(),
            'updated_on' => time(),
            'related_to' => [
                'type' => 'purchase',
                'id' => 'purch_refund_invalid',
            ],
        ];

        $event = PaymentRefunded::fromPayload($payload);

        expect($event->payment)->toBeNull()
            ->and($event->getAmount())->toBe(0)
            ->and($event->getCurrency())->toBe('MYR')
            ->and($event->getPurchaseId())->toBeNull();
    });
});

describe('WebhookReceived event', function (): void {
    it('can create from purchase payload', function (): void {
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
            ->and($event->payout)->toBeNull();
    });

    it('can create from payout payload', function (): void {
        $payload = [
            'id' => 'payout_123',
            'type' => 'payout',
            'event_type' => 'payout.success',
            'status' => 'success',
            'created_on' => time(),
            'updated_on' => time(),
            'payment' => [
                'amount' => 50000,
                'currency' => 'MYR',
                'net_amount' => 50000,
                'fee_amount' => 0,
                'pending_amount' => 0,
                'payment_type' => 'payout',
                'is_outgoing' => true,
            ],
            'client' => [
                'email' => 'john@example.com',
                'full_name' => 'John Doe',
            ],
            'brand_id' => 'brand_123',
            'transaction_data' => ['attempts' => []],
            'is_test' => true,
        ];

        $event = WebhookReceived::fromPayload($payload);

        expect($event->payout)->toBeInstanceOf(PayoutData::class)
            ->and($event->purchase)->toBeNull();
    });

    it('can create from payment payload', function (): void {
        $payload = [
            'id' => 'pay_123',
            'type' => 'payment',
            'event_type' => 'payment.refunded',
            'created_on' => time(),
            'updated_on' => time(),
            'payment' => [
                'amount' => 10000,
                'currency' => 'MYR',
                'net_amount' => 10000,
                'fee_amount' => 0,
                'pending_amount' => 0,
                'payment_type' => 'refund',
                'is_outgoing' => true,
            ],
            'related_to' => [
                'type' => 'purchase',
                'id' => 'purch_123',
            ],
            'reference' => 'REF-123',
            'is_test' => true,
        ];

        $event = WebhookReceived::fromPayload($payload);

        expect($event->payment)->toBeInstanceOf(PaymentData::class)
            ->and($event->purchase)->toBeNull()
            ->and($event->getAmount())->toBe(10000)
            ->and($event->getCurrency())->toBe('MYR')
            ->and($event->getPurchaseId())->toBe('purch_123');
    });

    it('fails closed for malformed payment payloads without money fields', function (): void {
        $payload = [
            'id' => 'pay_invalid_123',
            'type' => 'payment',
            'event_type' => 'payment.refunded',
            'created_on' => time(),
            'updated_on' => time(),
            'related_to' => [
                'type' => 'purchase',
                'id' => 'purch_invalid_123',
            ],
            'reference' => 'REF-INVALID-123',
        ];

        $event = WebhookReceived::fromPayload($payload);

        expect($event->payment)->toBeNull()
            ->and($event->getAmount())->toBe(0)
            ->and($event->getCurrency())->toBe('MYR')
            ->and($event->getPurchaseId())->toBe('purch_invalid_123');
    });

    it('does not create data for an unsupported resource type', function (): void {
        $payload = [
            'id' => 'unknown_123',
            'type' => 'unknown_resource',
            'event_type' => 'unknown.event',
            'created_on' => time(),
            'updated_on' => time(),
            'is_test' => true,
        ];

        $event = WebhookReceived::fromPayload($payload);

        expect($event->payment)->toBeNull()
            ->and($event->purchase)->toBeNull()
            ->and($event->payout)->toBeNull();
    });

    it('returns null for unknown event type', function (): void {
        $payload = ['event_type' => 'unknown.event', 'is_test' => true];
        $event = new WebhookReceived('unknown.event', $payload);

        expect($event->getEventTypeEnum())->toBeNull();
    });

    it('provides correct data accessors', function (): void {
        $payload = [
            'event_type' => 'purchase.paid',
            'id' => 'purch_xyz',
            'reference' => 'REF-123',
            'client_id' => 'client_abc',
            'purchase' => [
                'total' => 15000,
                'currency' => 'USD',
            ],
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

    it('returns default values for missing data', function (): void {
        $event = new WebhookReceived('unknown', []);

        expect($event->getReference())->toBeNull()
            ->and($event->getPurchaseId())->toBeNull()
            ->and($event->getClientId())->toBeNull()
            ->and($event->getAmount())->toBe(0)
            ->and($event->getCurrency())->toBe('MYR')
            ->and($event->isTest())->toBeFalse();
    });

    it('gets amount from nested purchase data', function (): void {
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
