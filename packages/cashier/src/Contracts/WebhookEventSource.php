<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

interface WebhookEventSource
{
    /** @return array<string, mixed> */
    public function fetchWebhookEvent(string $eventId): array;
}
