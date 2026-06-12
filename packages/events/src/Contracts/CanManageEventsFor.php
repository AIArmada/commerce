<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface CanManageEventsFor
{
    public function canManageEventsFor(mixed $manager, string $ability, mixed $target = null): bool;
}
