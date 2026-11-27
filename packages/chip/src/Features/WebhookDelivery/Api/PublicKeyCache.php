<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\WebhookDelivery\Api;

use Closure;

interface PublicKeyCache
{
    /** @param Closure(): string $fetch */
    public function remember(string $cacheKey, Closure $fetch): string;
}
