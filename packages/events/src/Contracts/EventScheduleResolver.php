<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventScheduleResolver
{
    /**
     * @return iterable<int|string, mixed>
     */
    public function resolve(string $eventId): iterable;
}
