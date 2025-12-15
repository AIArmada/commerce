<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Events\PurchaseHold;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Chip\Events\PurchasePreauthorized;
use AIArmada\Chip\Events\PurchaseSubscriptionChargeFailure;

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
                            'message' => 'Insufficient funds',
                            'code' => 'INSUFFICIENT_FUNDS',
                        ],
                    ],
                ],
            ],
            'is_test' => true,
        ];

        $event = PurchasePaymentFailure::fromPayload($payload);

        expect($event->getErrorMessage())->toBe('Insufficient funds')
            ->and($event->getErrorCode())->toBe('INSUFFICIENT_FUNDS');
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

describe('PurchaseSubscriptionChargeFailure event', function (): void {
    it('can be created from payload', function (): void {
        $payload = [
            'id' => 'purch_sub_fail',
            'status' => 'error',
            'type' => 'purchase',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => [
                'total' => 10000,
                'currency' => 'MYR',
                'products' => [['name' => 'Subscription', 'price' => 10000, 'quantity' => 1]],
                'metadata' => ['subscription_id' => 'sub_123'],
            ],
            'is_test' => true,
            'recurring_token' => 'token_123',
        ];

        $event = PurchaseSubscriptionChargeFailure::fromPayload($payload);

        expect($event)->toBeInstanceOf(PurchaseSubscriptionChargeFailure::class)
            ->and($event->eventType())->toBe(WebhookEventType::PurchaseSubscriptionChargeFailure);
    });

    it('returns subscription metadata', function (): void {
        $payload = [
            'id' => 'purch_sub_fail',
            'status' => 'error',
            'type' => 'purchase',
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => [
                'total' => 10000,
                'currency' => 'MYR',
                'products' => [['name' => 'Subscription', 'price' => 10000, 'quantity' => 1]],
                'metadata' => ['subscription_id' => 'sub_123'],
            ],
            'is_test' => true,
            'recurring_token' => 'token_abc',
        ];

        $event = PurchaseSubscriptionChargeFailure::fromPayload($payload);

        expect($event->hasRecurringToken())->toBeTrue()
            ->and($event->getRecurringToken())->toBe('token_abc');
    });
});

/**
 * Helper function to create purchase payload.
 */
function createLowCoveragePayload(string $status): array
{
    return [
        'id' => 'purch_' . uniqid(),
        'status' => $status,
        'type' => 'purchase',
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
