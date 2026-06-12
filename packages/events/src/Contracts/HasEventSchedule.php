<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use DateTimeInterface;

interface HasEventSchedule
{
    public function eventStartsAt(): ?DateTimeInterface;

    public function eventEndsAt(): ?DateTimeInterface;

    public function eventTimezone(): ?string;
}
