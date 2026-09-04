<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Enums\WebhookEventType;

/**
 * Event fired when CHIP cannot capture an authorized purchase.
 */
final class PurchaseCaptureFailure extends PurchaseEvent
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PurchaseCaptureFailure;
    }
}
