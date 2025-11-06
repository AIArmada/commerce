<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\SendWebhook;

describe('SendWebhook data object', function (): void {
    it('parses webhook payloads with malformed callback key', function (): void {
        $payload = [
            'id' => 4,
            'name' => 'Send webhook',
            'public_key' => 'pk',
            'callback_url"' => 'https://example.com/webhook',
            'email' => 'ops@example.com',
            'event_hooks' => ['bank_account_status', 'send_instruction_status'],
            'created_at' => 1712074800,
            'updated_at' => '2024-04-02T10:00:00Z',
        ];

        $webhook = SendWebhook::fromArray($payload);

        expect($webhook->id)->toBe(4)
            ->and($webhook->name)->toBe('Send webhook')
            ->and($webhook->public_key)->toBe('pk')
            ->and($webhook->callback_url)->toBe('https://example.com/webhook')
            ->and($webhook->email)->toBe('ops@example.com')
            ->and($webhook->event_hooks)->toBe(['bank_account_status', 'send_instruction_status']);

        expect($webhook->handlesEvent('send_instruction_status'))->toBeTrue()
            ->and($webhook->handlesEvent('budget_allocation_status'))->toBeFalse();

        expect($webhook->getCreatedAt()->toDateString())->toBe('2024-04-02')
            ->and($webhook->getUpdatedAt()->toIso8601ZuluString())->toBe('2024-04-02T10:00:00Z');

        expect($webhook->toArray())->toMatchArray([
            'id' => 4,
            'callback_url' => 'https://example.com/webhook',
        ]);
    });
});
