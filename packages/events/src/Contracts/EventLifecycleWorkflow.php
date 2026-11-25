<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use DateTimeInterface;

interface EventLifecycleWorkflow
{
    public function publish(string $eventId): void;

    public function cancel(string $targetType, string $targetId, ?string $reason = null): void;

    public function postpone(string $targetType, string $targetId, ?string $reason = null): void;

    public function delay(
        string $targetType,
        string $targetId,
        ?string $reason = null,
        ?DateTimeInterface $expectedStartsAt = null,
    ): void;

    /**
     * @param  array<string, mixed>  $options
     */
    public function reschedule(
        string $targetType,
        string $targetId,
        DateTimeInterface $newStartsAt,
        ?DateTimeInterface $newEndsAt = null,
        array $options = [],
    ): string;

    public function complete(string $targetType, string $targetId): void;

    public function archive(string $targetType, string $targetId, ?string $reason = null): void;
}
