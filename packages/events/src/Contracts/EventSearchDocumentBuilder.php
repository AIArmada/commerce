<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventSearchDocumentBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function buildForEvent(string $eventId): array;

    /**
     * @return array<string, mixed>
     */
    public function buildForOccurrence(string $occurrenceId): array;

    /**
     * @return array<string, mixed>
     */
    public function buildForSession(string $sessionId): array;
}
