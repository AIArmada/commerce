<?php

declare(strict_types=1);

namespace AIArmada\Chip\Tests\Unit;

use AIArmada\Chip\Support\ResolveWebhookPurchaseId;
use AIArmada\Chip\Tests\TestCase;

uses(TestCase::class);

describe('fromPaymentPayload', function (): void {
    it('resolves purchase ID from payment webhook payload', function (): void {
        $payload = [
            'type' => 'payment',
            'related_to' => ['type' => 'purchase', 'id' => 'purchase-123'],
            'id' => 'payment-456',
        ];

        expect(ResolveWebhookPurchaseId::fromPaymentPayload($payload))->toBe('purchase-123');
    });

    it('returns null for non-payment payloads', function (): void {
        $payload = [
            'type' => 'purchase',
            'id' => 'purchase-123',
        ];

        expect(ResolveWebhookPurchaseId::fromPaymentPayload($payload))->toBeNull();
    });

    it('returns null when related_to is not a purchase', function (): void {
        $payload = [
            'type' => 'payment',
            'related_to' => ['type' => 'client', 'id' => 'client-123'],
        ];

        expect(ResolveWebhookPurchaseId::fromPaymentPayload($payload))->toBeNull();
    });

    it('returns null when related_to.id is empty', function (): void {
        $payload = [
            'type' => 'payment',
            'related_to' => ['type' => 'purchase', 'id' => ''],
        ];

        expect(ResolveWebhookPurchaseId::fromPaymentPayload($payload))->toBeNull();
    });

    it('returns null when payload has no type', function (): void {
        expect(ResolveWebhookPurchaseId::fromPaymentPayload([]))->toBeNull();
    });
});

describe('fromAnyPayload', function (): void {
    it('resolves purchase ID from payment webhook via related_to', function (): void {
        $payload = [
            'type' => 'payment',
            'related_to' => ['type' => 'purchase', 'id' => 'purchase-123'],
        ];

        expect(ResolveWebhookPurchaseId::fromAnyPayload($payload))->toBe('purchase-123');
    });

    it('falls back to payload id for purchase payloads', function (): void {
        $payload = [
            'type' => 'purchase',
            'id' => 'purchase-789',
        ];

        expect(ResolveWebhookPurchaseId::fromAnyPayload($payload))->toBe('purchase-789');
    });

    it('returns null for empty payload', function (): void {
        expect(ResolveWebhookPurchaseId::fromAnyPayload([]))->toBeNull();
    });
});
