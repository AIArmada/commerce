<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Persistence;

use AIArmada\Jnt\Features\Tracking\Models\JntTrackingEvent;

final class TrackingEventWriter implements \AIArmada\Jnt\Features\Tracking\Api\StoreTrackingEvents
{
    /** @param list<array<string, mixed>> $rows */
    public function insertMissing(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            JntTrackingEvent::query()->withoutOwnerScope()->insertOrIgnore($chunk);
        }
    }
}
