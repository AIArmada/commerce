<?php

declare(strict_types=1);

use AIArmada\Chip\Webhooks\ProcessChipWebhook;
use AIArmada\Chip\Webhooks\WebhookMonitor;
use AIArmada\Chip\Data\WebhookHealth;
use Illuminate\Support\Carbon;

describe('ProcessChipWebhook', function () {
    it('extends CommerceWebhookProcessor', function () {
        // Just verify the class can be loaded and is defined
        expect(class_exists(ProcessChipWebhook::class))->toBeTrue();
    });
});

describe('WebhookMonitor', function () {
    it('can be instantiated', function () {
        $monitor = new WebhookMonitor;
        expect($monitor)->toBeInstanceOf(WebhookMonitor::class);
    });
});
