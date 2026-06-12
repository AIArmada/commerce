<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use DateTimeInterface;

interface EventLifecycleWorkflow
{
    public function publish(Event $event): void;

    public function cancel(Event|EventOccurrence $target, ?string $reason = null): void;

    public function postpone(Event|EventOccurrence $target, ?string $reason = null): void;

    public function delay(EventOccurrence $occurrence, ?string $reason = null, ?DateTimeInterface $expectedStartsAt = null): void;

    public function reschedule(EventOccurrence $occurrence, DateTimeInterface $newStartsAt, DateTimeInterface $newEndsAt, array $options = []): EventOccurrence;

    public function complete(Event|EventOccurrence $target): void;

    public function archive(Event|EventOccurrence $target, ?string $reason = null): void;
}
