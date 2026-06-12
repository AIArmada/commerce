<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

trait PublishesEventUpdates
{
    public function publishUpdate(array $data): EventUpdate
    {
        return EventUpdate::query()->create(array_merge(
            [
                'event_id' => $this->getKey(),
                'update_type' => $data['update_type'] ?? 'notice',
                'title' => $data['title'] ?? 'Event Update',
                'message' => $data['message'] ?? 'An update has been published.',
                'severity' => $data['severity'] ?? 'info',
                'visibility' => $data['visibility'] ?? 'public',
                'published_at' => $data['published_at'] ?? CarbonImmutable::now(),
            ],
            Arr::except($data, ['event_id']),
        ));
    }
}
