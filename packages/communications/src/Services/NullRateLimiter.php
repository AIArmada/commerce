<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\Communications\Contracts\RateLimiter;

final class NullRateLimiter implements RateLimiter
{
    public function isRateLimited(
        ?string $recipientType,
        ?string $recipientId,
        string $channel,
        string $category,
    ): bool {
        return false;
    }

    public function nextAllowedAt(
        ?string $recipientType,
        ?string $recipientId,
        string $channel,
        string $category,
    ): ?string {
        return null;
    }
}
