<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Events\PurchaseHold;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Chip\Events\PurchasePreauthorized;

describe('PurchasePaymentFailure event', function (): void {
    it('can be created from payload', function (): void {
        $payload = createLowCoveragePayload('payment_failure');
        $event = PurchasePaymentFailure::fromPayload($payload);

        expect($event)->toBeInstanceOf(PurchasePaymentFailure::class)
            ->and($event->eventType())->toBe(WebhookEventType::PurchasePaymentFailure);
    });

    it('returns error message from last attempt', function (): void {
        $payload = [
            'id' => 'purch_test',
            'status' => 'error',
            'type' => 'purchase',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => [
                'total' => 10000,
                'currency' => 'MYR',
                'products' => [['name' => 'Test', 'price' => 10000, 'quantity' => 1]],
            ],
            'transaction_data' => [
                'attempts' => [
                    [
                        'successful' => false,
                        'error' => [
                            'message' => 'Card declined',
                            'code' => 'CARD_DECLINED',
                        ],
                    ],
                    [
                        'successful' => false,
                        'error' => [
                            'message' => 'Insufficient funds',
                            'code' => 'INSUFFICIENT_FUNDS',
                        ],
                    ],
                ],
            ],
            'is_test' => true,
        ];

        $event = PurchasePaymentFailure::fromPayload($payload);

        expect($event->getErrorMessage())->toBe('Card declined')
            ->and($event->getErrorCode())->toBe('CARD_DECLINED');
    });

    it('returns null when no error in attempts', function (): void {
        $payload = createLowCoveragePayload('payment_failure');
        $event = PurchasePaymentFailure::fromPayload($payload);

        expect($event->getErrorMessage())->toBeNull()
            ->and($event->getErrorCode())->toBeNull();
    });
});

describe('PurchaseHold event', function (): void {
    it('can be created from payload', function (): void {
        $payload = createLowCoveragePayload('hold');
        $event = PurchaseHold::fromPayload($payload);

        expect($event)->toBeInstanceOf(PurchaseHold::class)
            ->and($event->eventType())->toBe(WebhookEventType::PurchaseHold);
    });

    it('returns correct event type value', function (): void {
        $payload = createLowCoveragePayload('hold');
        $event = PurchaseHold::fromPayload($payload);

        expect($event->getEventTypeValue())->toBe('purchase.hold');
    });
});

describe('PurchasePreauthorized event', function (): void {
    it('can be created from payload', function (): void {
        $payload = createLowCoveragePayload('preauthorized');
        $event = PurchasePreauthorized::fromPayload($payload);

        expect($event)->toBeInstanceOf(PurchasePreauthorized::class)
            ->and($event->eventType())->toBe(WebhookEventType::PurchasePreauthorized);
    });

    it('returns correct event type value', function (): void {
        $payload = createLowCoveragePayload('preauthorized');
        $event = PurchasePreauthorized::fromPayload($payload);

        expect($event->getEventTypeValue())->toBe('purchase.preauthorized');
    });
});

/**
 * Helper function to create purchase payload.
 */
function createLowCoveragePayload(string $status): array
{
    $eventType = match ($status) {
        'payment_failure' => 'purchase.payment_failure',
        'hold' => 'purchase.hold',
        'preauthorized' => 'purchase.preauthorized',
        default => throw new InvalidArgumentException("Unsupported low-coverage event: {$status}"),
    };

    $purchaseStatus = match ($status) {
        'payment_failure' => 'error',
        default => $status,
    };

    return [
        'id' => 'purch_' . uniqid(),
        'status' => $purchaseStatus,
        'type' => 'purchase',
        'event_type' => $eventType,
        'created_on' => time(),
        'updated_on' => time(),
        'purchase' => [
            'total' => 10000,
            'currency' => 'MYR',
            'products' => [
                ['name' => 'Test Product', 'price' => 10000, 'quantity' => 1],
            ],
        ],
        'client' => [
            'email' => 'test@example.com',
            'full_name' => 'Test User',
        ],
        'is_test' => true,
    ];
}
