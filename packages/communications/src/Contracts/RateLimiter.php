<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

interface RateLimiter
{
    public function isRateLimited(
        ?string $recipientType,
        ?string $recipientId,
        string $channel,
        string $category,
    ): bool;

    public function nextAllowedAt(
        ?string $recipientType,
        ?string $recipientId,
        string $channel,
        string $category,
    ): ?string;
}
