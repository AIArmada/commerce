<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\Communications\Contracts\PreferenceResolver;

class NullPreferenceResolver implements PreferenceResolver
{
    public function isEnabled(
        ?string $recipientType,
        ?string $recipientId,
        string $channel,
        string $category,
    ): bool {
        return true;
    }

    public function isOptedIn(
        ?string $recipientType,
        ?string $recipientId,
        string $channel,
        string $category,
    ): ?bool {
        return null;
    }
}
