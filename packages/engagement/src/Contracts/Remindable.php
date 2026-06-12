<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

use DateTimeInterface;

interface Remindable
{
    public function remindableName(): string;

    public function reminderAnchorTime(string $anchorType, ?string $anchorCode = null): ?DateTimeInterface;

    /** @return array<string> */
    public function allowedReminderTypes(): array;
}
