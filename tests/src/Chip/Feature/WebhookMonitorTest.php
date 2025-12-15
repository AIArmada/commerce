<?php

declare(strict_types=1);

use AIArmada\Chip\Webhooks\WebhookMonitor;

describe('WebhookMonitor without database', function (): void {
    it('can be instantiated', function (): void {
        $monitor = new WebhookMonitor;
        expect($monitor)->toBeInstanceOf(WebhookMonitor::class);
    });
});
