<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use DateTimeInterface;

interface HasEventLifecycle
{
    public function publish(): void;

    public function cancel(?string $reason = null): void;

    public function postpone(?string $reason = null): void;

    public function delay(?string $reason = null, ?DateTimeInterface $expectedStartsAt = null): void;

    public function reschedule(DateTimeInterface $startsAt, DateTimeInterface $endsAt, array $options = []): void;

    public function complete(): void;

    public function archive(?string $reason = null): void;
}
