<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Api;

interface ReadWebhookOrder
{
    public function findByTrackingNumber(string $trackingNumber): ?WebhookOrder;

    public function lockForUpdate(WebhookOrder $expected): WebhookOrder;
}
