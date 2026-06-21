<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

interface PreferenceResolver
{
    public function isEnabled(
        ?string $recipientType,
        ?string $recipientId,
        string $channel,
        string $category,
    ): bool;

    public function isOptedIn(
        ?string $recipientType,
        ?string $recipientId,
        string $channel,
        string $category,
    ): ?bool;
}
