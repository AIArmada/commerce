<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Changes\Api;

interface ChangesApi
{
    /**
     * @param  array<string, mixed>  $oldValue
     * @param  array<string, mixed>  $newValue
     */
    public function dispatchChange(
        string $eventId,
        string $changeType,
        string $changeCategory = 'administration',
        string $impactLevel = 'low',
        bool $requiresNotification = false,
        ?string $reason = null,
        ?string $occurrenceId = null,
        ?string $sessionId = null,
        array $oldValue = [],
        array $newValue = [],
    ): void;
}
