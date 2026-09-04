<?php

declare(strict_types=1);

use AIArmada\Chip\Data\PayoutData;
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Events\PayoutFailed;
use AIArmada\Chip\Events\PayoutPending;
use AIArmada\Chip\Events\PayoutSuccess;

describe('PayoutEvent base class', function (): void {
    function createPayoutPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'payout_test123',
            'type' => 'payout',
            'created_on' => time(),
            'updated_on' => time(),
            'status' => 'success',
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
            'reference' => 'PAYOUT-REF-123',
            'is_test' => true,
        ], $overrides);
    }

    it('can create PayoutSuccess from payload', function (): void {
        $payload = createPayoutPayload();

        $event = PayoutSuccess::fromPayload($payload);

        expect($event)->toBeInstanceOf(PayoutSuccess::class)
            ->and($event->payout)->toBeInstanceOf(PayoutData::class)
            ->and($event->payload)->toBe($payload)
            ->and($event->eventType())->toBe(WebhookEventType::PayoutSuccess);
    });

    it('can create PayoutPending from payload', function (): void {
        $payload = createPayoutPayload(['status' => 'pending']);

        $event = PayoutPending::fromPayload($payload);

        expect($event)->toBeInstanceOf(PayoutPending::class)
            ->and($event->eventType())->toBe(WebhookEventType::PayoutPending);
    });

    it('can create PayoutFailed from payload', function (): void {
        $payload = createPayoutPayload([
            'status' => 'error',
            'transaction_data' => [
                'attempts' => [[
                    'successful' => false,
                    'error' => ['message' => 'Insufficient funds', 'code' => 'INSUFFICIENT_FUNDS'],
                ]],
            ],
        ]);

        $event = PayoutFailed::fromPayload($payload);

        expect($event)->toBeInstanceOf(PayoutFailed::class)
            ->and($event->eventType())->toBe(WebhookEventType::PayoutFailed)
            ->and($event->getErrorMessage())->toBe('Insufficient funds')
            ->and($event->getErrorCode())->toBe('INSUFFICIENT_FUNDS');
    });

    it('provides correct getPayoutId', function (): void {
        $payload = createPayoutPayload(['id' => 'payout_xyz789']);
        $event = PayoutSuccess::fromPayload($payload);

        expect($event->getPayoutId())->toBe('payout_xyz789');
    });

    it('provides correct getAmount', function (): void {
        $payload = createPayoutPayload(['payment' => ['amount' => 75000]]);
        $event = PayoutSuccess::fromPayload($payload);

        expect($event->getAmount())->toBe(75000);
    });

    it('provides correct getCurrency', function (): void {
        $payload = createPayoutPayload(['payment' => ['currency' => 'USD']]);
        $event = PayoutSuccess::fromPayload($payload);

        expect($event->getCurrency())->toBe('USD');
    });

    it('provides correct getStatus', function (): void {
        $payload = createPayoutPayload(['status' => 'pending']);
        $event = PayoutPending::fromPayload($payload);

        expect($event->getStatus())->toBe('pending');
    });

    it('provides correct getReference', function (): void {
        $payload = createPayoutPayload(['reference' => 'MY-PAYOUT-REF']);
        $event = PayoutSuccess::fromPayload($payload);

        expect($event->getReference())->toBe('MY-PAYOUT-REF');
    });

    it('provides correct getRecipientName', function (): void {
        $payload = createPayoutPayload([
            'client' => [
                'email' => 'jane@example.com',
                'full_name' => 'Jane Smith',
            ],
        ]);
        $event = PayoutSuccess::fromPayload($payload);

        expect($event->getRecipientName())->toBe('Jane Smith');
    });

    it('provides documented card details', function (): void {
        $payload = createPayoutPayload([
            'recipient_card_country' => 'MY',
            'recipient_card_brand' => 'visa',
            'sender_name' => 'Commerce Ltd',
            'execution_url' => 'https://example.test/payout',
        ]);
        $event = PayoutSuccess::fromPayload($payload);

        expect($event->getRecipientCardCountry())->toBe('MY')
            ->and($event->getRecipientCardBrand())->toBe('visa')
            ->and($event->getSenderName())->toBe('Commerce Ltd')
            ->and($event->getExecutionUrl())->toBe('https://example.test/payout');
    });

    it('correctly checks isTest', function (): void {
        $payload = createPayoutPayload(['is_test' => true]);
        $event = PayoutSuccess::fromPayload($payload);

        expect($event->isTest())->toBeTrue();

        $livePayout = createPayoutPayload(['is_test' => false]);
        $liveEvent = PayoutSuccess::fromPayload($livePayout);

        expect($liveEvent->isTest())->toBeFalse();
    });

    it('returns correct event types for all payout events', function (): void {
        $payload = createPayoutPayload();
        $payoutData = PayoutData::from($payload);

        $events = [
            PayoutSuccess::class => WebhookEventType::PayoutSuccess,
            PayoutPending::class => WebhookEventType::PayoutPending,
            PayoutFailed::class => WebhookEventType::PayoutFailed,
        ];

        foreach ($events as $eventClass => $expectedType) {
            $event = new $eventClass($payoutData, $payload);
            expect($event->eventType())->toBe($expectedType, "Failed for {$eventClass}");
        }
    });
});

describe('PayoutFailed specific methods', function (): void {
    it('returns error message from payout data', function (): void {
        $payload = [
            'id' => 'payout_fail',
            'type' => 'payout',
            'created_on' => time(),
            'updated_on' => time(),
            'status' => 'error',
            'payment' => [
                'amount' => 10000,
                'currency' => 'MYR',
                'net_amount' => 10000,
                'fee_amount' => 0,
                'pending_amount' => 0,
                'payment_type' => 'payout',
                'is_outgoing' => true,
            ],
            'client' => ['email' => 'recipient@example.com'],
            'brand_id' => 'brand_123',
            'transaction_data' => [
                'attempts' => [[
                    'successful' => false,
                    'error' => ['message' => 'Bank rejected transfer', 'code' => 'BANK_REJECTED'],
                ]],
            ],
            'is_test' => true,
        ];

        $event = PayoutFailed::fromPayload($payload);

        expect($event->getErrorMessage())->toBe('Bank rejected transfer')
            ->and($event->getErrorCode())->toBe('BANK_REJECTED');
    });

    it('returns null for missing error info', function (): void {
        $payload = [
            'id' => 'payout_fail',
            'type' => 'payout',
            'created_on' => time(),
            'updated_on' => time(),
            'status' => 'error',
            'payment' => [
                'amount' => 10000,
                'currency' => 'MYR',
                'net_amount' => 10000,
                'fee_amount' => 0,
                'pending_amount' => 0,
                'payment_type' => 'payout',
                'is_outgoing' => true,
            ],
            'client' => ['email' => 'recipient@example.com'],
            'brand_id' => 'brand_123',
            'transaction_data' => ['attempts' => []],
            'is_test' => true,
        ];

        $event = PayoutFailed::fromPayload($payload);

        expect($event->getErrorMessage())->toBeNull()
            ->and($event->getErrorCode())->toBeNull();
    });
});
