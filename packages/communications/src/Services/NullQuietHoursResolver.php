<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\Communications\Contracts\QuietHoursResolver;

class NullQuietHoursResolver implements QuietHoursResolver
{
    public function isInQuietHours(
        ?string $recipientType,
        ?string $recipientId,
    ): bool {
        return false;
    }

    public function nextAllowedAt(
        ?string $recipientType,
        ?string $recipientId,
    ): ?string {
        return null;
    }
}
