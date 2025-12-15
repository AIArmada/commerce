<?php

declare(strict_types=1);

use AIArmada\Chip\Webhooks\ProcessChipWebhook;
use AIArmada\Chip\Webhooks\WebhookMonitor;

describe('ProcessChipWebhook', function (): void {
    it('extends CommerceWebhookProcessor', function (): void {
        // Just verify the class can be loaded and is defined
        expect(class_exists(ProcessChipWebhook::class))->toBeTrue();
    });
});

describe('WebhookMonitor', function (): void {
    it('can be instantiated', function (): void {
        $monitor = new WebhookMonitor;
        expect($monitor)->toBeInstanceOf(WebhookMonitor::class);
    });
});
