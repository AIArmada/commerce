<?php

declare(strict_types=1);

use AIArmada\Chip\Data\WebhookHealth;
use AIArmada\Chip\Webhooks\WebhookMonitor;
use Illuminate\Support\Carbon;

describe('WebhookMonitor without database', function () {
    it('can be instantiated', function () {
        $monitor = new WebhookMonitor;
        expect($monitor)->toBeInstanceOf(WebhookMonitor::class);
    });
});
