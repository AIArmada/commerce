<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\WebhookEventType;

describe('WebhookEventType enum', function (): void {
    it('does not expose undocumented billing events', function (): void {
        expect(WebhookEventType::fromString('billing_template_client.subscription_billing_cancelled'))->toBeNull();
        expect(WebhookEventType::PurchasePaid->isPurchaseEvent())->toBeTrue();
    });

});
