<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use DateTimeInterface;

interface EventAvailabilityChecker
{
    public function isAvailable(mixed $blockable, DateTimeInterface $startsAt, DateTimeInterface $endsAt, array $context = []): bool;
}
