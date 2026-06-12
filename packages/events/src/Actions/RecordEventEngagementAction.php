<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\EventAttendance;
use AIArmada\Events\Models\EventEngagement;
use Lorisleiva\Actions\Concerns\AsAction;

final class RecordEventEngagementAction
{
    use AsAction;

    public function handle(EventAttendance $attendance, string $engagementType, array $metadata = []): EventEngagement
    {
        return EventEngagement::query()->create([
            'event_attendance_id' => $attendance->getKey(),
            'engagement_type' => $engagementType,
            'metadata' => $metadata,
        ]);
    }
}
