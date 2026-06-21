<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

interface QuietHoursResolver
{
    public function isInQuietHours(
        ?string $recipientType,
        ?string $recipientId,
    ): bool;

    public function nextAllowedAt(
        ?string $recipientType,
        ?string $recipientId,
    ): ?string;
}
