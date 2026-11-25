<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Attendance\Actions;

use AIArmada\Events\Features\Attendance\Models\EventAttendanceLog;
use Illuminate\Support\Facades\DB;

final class DeleteAttendanceDependents
{
    public function handle(string $attendanceId): void
    {
        DB::transaction(function () use ($attendanceId): void {
            EventAttendanceLog::query()
                ->where('event_attendance_id', $attendanceId)
                ->chunkById(200, function (iterable $logs): void {
                    foreach ($logs as $log) {
                        $log->delete();
                    }
                });
        });
    }
}
