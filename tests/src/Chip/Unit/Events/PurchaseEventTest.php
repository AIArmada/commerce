<?php

declare(strict_types=1);

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Events\PurchaseCancelled;
use AIArmada\Chip\Events\PurchaseCaptured;
use AIArmada\Chip\Events\PurchaseCreated;
use AIArmada\Chip\Events\PurchaseHold;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Chip\Events\PurchasePendingCapture;
use AIArmada\Chip\Events\PurchasePendingCharge;
use AIArmada\Chip\Events\PurchasePendingExecute;
use AIArmada\Chip\Events\PurchasePendingRecurringTokenDelete;
use AIArmada\Chip\Events\PurchasePendingRefund;
use AIArmada\Chip\Events\PurchasePendingRelease;
use AIArmada\Chip\Events\PurchasePreauthorized;
use AIArmada\Chip\Events\PurchaseRecurringTokenDeleted;
use AIArmada\Chip\Events\PurchaseReleased;
use AIArmada\Chip\Events\PurchaseSubscriptionChargeFailure;

describe('PurchaseEvent base class', function () {
    function createPurchasePayload(array $overrides = []): array
    {
        $defaults = [
            'id' => 'purch_test123',
            'status' => 'paid',
            'type' => 'purchase',
            'created_on' => time(),
            'updated_on' => time(),
            'currency' => 'MYR',
            'reference' => 'REF-123',
            'client_id' => 'client_abc123',
            'client' => [
                'email' => 'test@example.com',
                'full_name' => 'Test User',
            ],
            'purchase' => [
                'total' => 10000,
                'currency' => 'MYR',
                'products' => [['name' => 'Test Product', 'price' => 10000, 'quantity' => 1]],
                'metadata' => ['order_id' => 'ord-123'],
            ],
            'transaction_data' => [
                'payment_method' => 'fpx',
                'attempts' => [],
            ],
            'recurring_token' => 'token_xyz789',
            'is_test' => true,
            'payment' => [
                'amount' => 10000,
                'currency' => 'MYR',
                'net_amount' => 9900,
                'fee_amount' => 100,
            ],
        ];

        return array_replace_recursive($defaults, $overrides);
    }

    it('can create PurchasePaid from payload', function () {
        $payload = createPurchasePayload();

        $event = PurchasePaid::fromPayload($payload);

        expect($event)->toBeInstanceOf(PurchasePaid::class)
            ->and($event->purchase)->toBeInstanceOf(PurchaseData::class)
            ->and($event->payload)->toBe($payload)
            ->and($event->eventType())->toBe(WebhookEventType::PurchasePaid);
    });

    it('returns correct event type for all purchase events', function () {
        $payload = createPurchasePayload();
        $purchaseData = PurchaseData::from($payload);

        $events = [
            PurchasePaid::class => WebhookEventType::PurchasePaid,
            PurchaseCancelled::class => WebhookEventType::PurchaseCancelled,
            PurchaseCaptured::class => WebhookEventType::PurchaseCaptured,
            PurchaseCreated::class => WebhookEventType::PurchaseCreated,
            PurchaseHold::class => WebhookEventType::PurchaseHold,
            PurchasePaymentFailure::class => WebhookEventType::PurchasePaymentFailure,
            PurchasePendingCapture::class => WebhookEventType::PurchasePendingCapture,
            PurchasePendingCharge::class => WebhookEventType::PurchasePendingCharge,
            PurchasePendingExecute::class => WebhookEventType::PurchasePendingExecute,
            PurchasePendingRecurringTokenDelete::class => WebhookEventType::PurchasePendingRecurringTokenDelete,
            PurchasePendingRefund::class => WebhookEventType::PurchasePendingRefund,
            PurchasePendingRelease::class => WebhookEventType::PurchasePendingRelease,
            PurchasePreauthorized::class => WebhookEventType::PurchasePreauthorized,
            PurchaseRecurringTokenDeleted::class => WebhookEventType::PurchaseRecurringTokenDeleted,
            PurchaseReleased::class => WebhookEventType::PurchaseReleased,
            PurchaseSubscriptionChargeFailure::class => WebhookEventType::PurchaseSubscriptionChargeFailure,
        ];

        foreach ($events as $eventClass => $expectedType) {
            $event = new $eventClass($purchaseData, $payload);
            expect($event->eventType())->toBe($expectedType, "Failed for {$eventClass}");
        }
    });

    it('provides correct getEventTypeValue', function () {
        $payload = createPurchasePayload();
        $purchaseData = PurchaseData::from($payload);
        $event = new PurchasePaid($purchaseData, $payload);

        expect($event->getEventTypeValue())->toBe('purchase.paid');
    });

    it('provides correct getReference', function () {
        $payload = createPurchasePayload(['reference' => 'MY-REF-456']);
        $purchaseData = PurchaseData::from($payload);
        $event = new PurchasePaid($purchaseData, $payload);

        expect($event->getReference())->toBe('MY-REF-456');
    });

    it('provides correct getPurchaseId', function () {
        $payload = createPurchasePayload(['id' => 'purch_abc123']);
        $purchaseData = PurchaseData::from($payload);
        $event = new PurchasePaid($purchaseData, $payload);

        expect($event->getPurchaseId())->toBe('purch_abc123');
    });

    it('provides correct getClientId', function () {
        $payload = createPurchasePayload(['client_id' => 'client_xyz']);
        $purchaseData = PurchaseData::from($payload);
        $event = new PurchasePaid($purchaseData, $payload);

        expect($event->getClientId())->toBe('client_xyz');
    });

    it('provides correct getAmount from purchase total', function () {
        $payload = createPurchasePayload([
            'purchase' => [
                'total' => 15000,
                'currency' => 'MYR',
                'products' => [],
            ],
        ]);
        $purchaseData = PurchaseData::from($payload);
        $event = new PurchasePaid($purchaseData, $payload);

        expect($event->getAmount())->toBe(15000);
    });

    it('provides correct getCurrency', function () {
        $payload = createPurchasePayload([
            'purchase' => [
                'total' => 5000,
                'currency' => 'USD',
                'products' => [],
            ],
        ]);
        $purchaseData = PurchaseData::from($payload);
        $event = new PurchasePaid($purchaseData, $payload);

        expect($event->getCurrency())->toBe('USD');
    });

    it('provides correct getStatus', function () {
        $payload = createPurchasePayload(['status' => 'hold']);
        $purchaseData = PurchaseData::from($payload);
        $event = new PurchaseHold($purchaseData, $payload);

        expect($event->getStatus())->toBe('hold');
    });

    it('provides correct getCustomerEmail from client', function () {
        $payload = createPurchasePayload([
            'client' => ['email' => 'customer@example.com', 'full_name' => 'John'],
        ]);
        $purchaseData = PurchaseData::from($payload);
        $event = new PurchasePaid($purchaseData, $payload);

        expect($event->getCustomerEmail())->toBe('customer@example.com');
    });

    it('provides correct getCustomerName from client', function () {
        $payload = createPurchasePayload([
            'client' => ['email' => 'test@test.com', 'full_name' => 'Jane Doe'],
        ]);
        $purchaseData = PurchaseData::from($payload);
        $event = new PurchasePaid($purchaseData, $payload);

        expect($event->getCustomerName())->toBe('Jane Doe');
    });

    it('provides correct getRecurringToken', function () {
        $payload = createPurchasePayload(['recurring_token' => 'rt_xyz123']);
        $purchaseData = PurchaseData::from($payload);
        $event = new PurchasePaid($purchaseData, $payload);

        expect($event->getRecurringToken())->toBe('rt_xyz123');
    });

    it('correctly checks hasRecurringToken', function () {
        $payload = createPurchasePayload(['recurring_token' => 'rt_xyz123']);
        $purchaseData = PurchaseData::from($payload);
        $event = new PurchasePaid($purchaseData, $payload);

        expect($event->hasRecurringToken())->toBeTrue();

        $payloadNoToken = createPurchasePayload(['recurring_token' => null]);
        $purchaseDataNoToken = PurchaseData::from($payloadNoToken);
        $event2 = new PurchasePaid($purchaseDataNoToken, $payloadNoToken);

        expect($event2->hasRecurringToken())->toBeFalse();
    });

    it('correctly checks isTest', function () {
        $payload = createPurchasePayload(['is_test' => true]);
        $purchaseData = PurchaseData::from($payload);
        $event = new PurchasePaid($purchaseData, $payload);

        expect($event->isTest())->toBeTrue();

        $payloadLive = createPurchasePayload(['is_test' => false]);
        $purchaseDataLive = PurchaseData::from($payloadLive);
        $event2 = new PurchasePaid($purchaseDataLive, $payloadLive);

        expect($event2->isTest())->toBeFalse();
    });

    it('provides correct getPaymentMethod', function () {
        $payload = createPurchasePayload([
            'transaction_data' => ['payment_method' => 'ewallet', 'attempts' => []],
        ]);
        $purchaseData = PurchaseData::from($payload);
        $event = new PurchasePaid($purchaseData, $payload);

        expect($event->getPaymentMethod())->toBe('ewallet');
    });

    it('provides correct getMetadata', function () {
        $payload = createPurchasePayload([
            'purchase' => [
                'total' => 10000,
                'currency' => 'MYR',
                'products' => [],
                'metadata' => ['order_id' => 'ord-456', 'cart_id' => 'cart-789'],
            ],
        ]);
        $purchaseData = PurchaseData::from($payload);
        $event = new PurchasePaid($purchaseData, $payload);

        $metadata = $event->getMetadata();
        expect($metadata)->toBeArray()
            ->and($metadata['order_id'])->toBe('ord-456')
            ->and($metadata['cart_id'])->toBe('cart-789');
    });

    it('provides correct getMetadataValue', function () {
        $payload = createPurchasePayload([
            'purchase' => [
                'total' => 10000,
                'currency' => 'MYR',
                'products' => [],
                'metadata' => ['order_id' => 'ord-456'],
            ],
        ]);
        $purchaseData = PurchaseData::from($payload);
        $event = new PurchasePaid($purchaseData, $payload);

        expect($event->getMetadataValue('order_id'))->toBe('ord-456');
        expect($event->getMetadataValue('non_existent', 'default'))->toBe('default');
    });
});

describe('PurchasePaid specific methods', function () {
    it('returns net amount from payment', function () {
        $payload = [
            'id' => 'purch_test',
            'status' => 'paid',
            'type' => 'purchase',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => [
                'total' => 10000,
                'currency' => 'MYR',
                'products' => [['name' => 'Test', 'price' => 10000, 'quantity' => 1]],
            ],
            'is_test' => true,
            'payment' => [
                'amount' => 10000,
                'net_amount' => 9800,
                'fee_amount' => 200,
                'currency' => 'MYR',
            ],
        ];

        $event = PurchasePaid::fromPayload($payload);
        expect($event->getNetAmount())->toBe(9800);
        expect($event->getFeeAmount())->toBe(200);
    });

    it('returns fallback amount when no payment', function () {
        $payload = [
            'id' => 'purch_test',
            'status' => 'paid',
            'type' => 'purchase',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => [
                'total' => 10000,
                'currency' => 'MYR',
                'products' => [['name' => 'Test', 'price' => 10000, 'quantity' => 1]],
            ],
            'is_test' => true,
        ];

        $event = PurchasePaid::fromPayload($payload);
        expect($event->getNetAmount())->toBe(10000);
        expect($event->getFeeAmount())->toBe(0);
    });
});

describe('PurchaseEvent edge cases', function () {
    it('handles empty client gracefully', function () {
        $payload = [
            'id' => 'purch_test',
            'status' => 'paid',
            'type' => 'purchase',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => [
                'total' => 10000,
                'currency' => 'MYR',
                'products' => [['name' => 'Test', 'price' => 10000, 'quantity' => 1]],
            ],
            'is_test' => true,
        ];

        $event = PurchasePaid::fromPayload($payload);
        // Empty client details return null when not provided
        expect($event->getCustomerEmail())->toBeNull()
            ->and($event->getCustomerName())->toBeNull();
    });

    it('handles empty transaction_data gracefully', function () {
        $payload = [
            'id' => 'purch_test',
            'status' => 'paid',
            'type' => 'purchase',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => [
                'total' => 10000,
                'currency' => 'MYR',
                'products' => [['name' => 'Test', 'price' => 10000, 'quantity' => 1]],
            ],
            'is_test' => true,
        ];

        $event = PurchasePaid::fromPayload($payload);
        // Empty transaction_data returns empty string, not null
        expect($event->getPaymentMethod())->toBe('');
    });

    it('handles null metadata gracefully', function () {
        $payload = [
            'id' => 'purch_test',
            'status' => 'paid',
            'type' => 'purchase',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => [
                'total' => 10000,
                'currency' => 'MYR',
                'products' => [['name' => 'Test', 'price' => 10000, 'quantity' => 1]],
            ],
            'is_test' => true,
        ];

        $event = PurchasePaid::fromPayload($payload);
        expect($event->getMetadata())->toBeNull();
    });
});
