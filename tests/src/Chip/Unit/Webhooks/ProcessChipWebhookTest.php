<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Webhooks\ProcessChipWebhook;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;

describe('ProcessChipWebhook class structure', function (): void {
    it('extends CommerceWebhookProcessor', function (): void {
        $reflection = new ReflectionClass(ProcessChipWebhook::class);
        expect($reflection->isSubclassOf(CommerceWebhookProcessor::class))->toBeTrue();
    });

    it('has processEvent method', function (): void {
        expect(method_exists(ProcessChipWebhook::class, 'processEvent'))->toBeTrue();
    });
});

describe('WebhookEventType enum for ProcessChipWebhook', function (): void {
    it('returns null for unknown event', function (): void {
        $type = WebhookEventType::fromString('unknown.event');
        expect($type)->toBeNull();
    });
});
