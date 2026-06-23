<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use DateTimeInterface;

interface EventLifecycleWorkflow
{
    public function publish(Event $event): void;

    public function cancel(Event | EventOccurrence | EventSession $target, ?string $reason = null): void;

    public function postpone(Event | EventOccurrence | EventSession $target, ?string $reason = null): void;

    public function delay(EventOccurrence | EventSession $target, ?string $reason = null, ?DateTimeInterface $expectedStartsAt = null): void;

    public function reschedule(EventOccurrence | EventSession $target, DateTimeInterface $newStartsAt, DateTimeInterface $newEndsAt, array $options = []): EventOccurrence | EventSession;

    public function complete(Event | EventOccurrence | EventSession $target): void;

    public function archive(Event | EventOccurrence $target, ?string $reason = null): void;
}
