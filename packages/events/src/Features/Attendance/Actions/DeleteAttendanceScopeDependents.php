<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Attendance\Actions;

use AIArmada\Events\Features\Attendance\Models\EventAttendance;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DeleteAttendanceScopeDependents
{
    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        $column = match ($scopeType) {
            'event' => 'event_id',
            'occurrence' => 'event_occurrence_id',
            'session' => 'event_session_id',
            'registration' => 'event_registration_id',
            default => throw new InvalidArgumentException(sprintf('Unknown event scope type [%s].', $scopeType)),
        };

        DB::transaction(function () use ($column, $scopeId): void {
            EventAttendance::query()
                ->where($column, $scopeId)
                ->chunkById(200, function (iterable $attendances): void {
                    foreach ($attendances as $attendance) {
                        $attendance->delete();
                    }
                });
        });
    }
}
