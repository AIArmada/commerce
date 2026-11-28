<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Api;

interface StoreTrackingEvents
{
    /** @param list<array<string, mixed>> $rows */
    public function insertMissing(array $rows): void;
}
