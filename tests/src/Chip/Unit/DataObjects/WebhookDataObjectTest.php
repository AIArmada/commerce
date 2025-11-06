<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\Purchase;
use AIArmada\Chip\DataObjects\Webhook;

describe('Webhook data object', function (): void {
    it('creates a webhook from array data', function (): void {
        $data = [
            'event' => 'purchase.paid',
            'data' => [
                'id' => 'purchase_123',
                'amount_in_cents' => 10000,
                'currency' => 'MYR',
                'status' => 'paid',
            ],
            'timestamp' => '2024-01-01T16:00:00Z',
            'headers' => ['X-Signature' => 'sig'],
            'payload' => ['raw' => 'payload'],
            'signature' => 'sig',
            'verified' => true,
            'processed' => true,
            'processed_at' => '2024-01-01T16:05:00Z',
            'processing_attempts' => 2,
        ];

        $webhook = Webhook::fromArray($data);

        expect($webhook->event)->toBe('purchase.paid');
        expect($webhook->event_type)->toBe('purchase.paid');
        expect($webhook->data)->toBe([
            'id' => 'purchase_123',
            'amount_in_cents' => 10000,
            'currency' => 'MYR',
            'status' => 'paid',
        ]);
        expect($webhook->timestamp)->toBe('2024-01-01T16:00:00Z');
        expect($webhook->headers)->toBe(['X-Signature' => 'sig']);
        expect($webhook->payload)->toBe(['raw' => 'payload']);
        expect($webhook->signature)->toBe('sig');
        expect($webhook->verified)->toBeTrue();
        expect($webhook->processed)->toBeTrue();
        expect($webhook->getProcessedAt()?->toIso8601ZuluString())->toBe('2024-01-01T16:05:00Z');
        expect($webhook->processing_attempts)->toBe(2);
    });

    it('extracts purchase from webhook data', function (): void {
        $webhook = Webhook::fromArray([
            'event' => 'purchase.created',
            'data' => [
                'id' => 'purchase_123',
                'amount_in_cents' => 10000,
                'currency' => 'MYR',
                'status' => 'created',
            ],
        ]);

        $purchase = $webhook->getPurchase();

        expect($purchase)->toBeInstanceOf(Purchase::class);
        expect($purchase->id)->toBe('purchase_123');
        expect($purchase->amountInCents)->toBe(10000);
    });

    it('returns null for non-purchase webhook events', function (): void {
        $webhook = Webhook::fromArray([
            'event' => 'send_instruction.completed',
            'data' => [
                'id' => 'send_123',
                'status' => 'completed',
            ],
        ]);

        expect($webhook->getPurchase())->toBeNull();
    });

    it('represents webhook configuration entries', function (): void {
        $webhook = Webhook::fromArray([
            'id' => 'wh_123',
            'type' => 'webhook',
            'created_on' => strtotime('2024-01-01T12:00:00Z'),
            'updated_on' => strtotime('2024-01-02T12:00:00Z'),
            'title' => 'Payments',
            'all_events' => false,
            'public_key' => 'pk',
            'events' => ['purchase.paid'],
            'callback' => 'https://example.com/webhook',
            'verified' => true,
            'processed' => true,
            'processed_at' => '2024-01-02T12:05:00Z',
            'processing_attempts' => 1,
            'processing_error' => null,
        ]);

        expect($webhook->handlesEvent('purchase.paid'))->toBeTrue();
        expect($webhook->handlesEvent('purchase.created'))->toBeFalse();
        expect($webhook->verified)->toBeTrue();
        expect($webhook->processed)->toBeTrue();
        expect($webhook->getProcessedAt()?->toIso8601ZuluString())->toBe('2024-01-02T12:05:00Z');
        expect($webhook->getCreatedAt()->toDateString())->toBe('2024-01-01');
        $asArray = $webhook->toArray();
        expect($asArray)->toMatchArray([
            'id' => 'wh_123',
            'callback' => 'https://example.com/webhook',
        ]);
        expect($asArray['verified'])->toBeTrue();
    });
});
