<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Webhooks\Api;

interface WebhookApi
{
    /** @param array<string, mixed> $payload */
    public function dispatch(string $type, array $payload): void;
}
