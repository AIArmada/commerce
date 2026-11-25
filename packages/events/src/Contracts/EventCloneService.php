<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventCloneService
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function cloneEvent(string $eventId, array $options = []): string;

    /**
     * @param  array<string, mixed>  $options
     */
    public function cloneOccurrence(string $occurrenceId, array $options = []): string;

    /**
     * @param  array<string, mixed>  $options
     */
    public function cloneSession(string $sessionId, array $options = []): string;
}
