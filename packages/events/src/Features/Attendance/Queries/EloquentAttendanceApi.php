<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Attendance\Queries;

use AIArmada\Events\Features\Attendance\Api\AttendanceApi;
use AIArmada\Events\Features\Attendance\Models\EventAttendance;
use AIArmada\Events\Features\Registrations\Api\EventRegistrationParticipantData;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use Illuminate\Database\Eloquent\Builder;

final class EloquentAttendanceApi implements AttendanceApi
{
    private const string ATTENDANCE_ATTENDED = 'attended';

    private const string ATTENDANCE_DID_NOT_ATTEND = 'did_not_attend';

    public function __construct(
        private readonly RegistrationsApi $registrations,
    ) {}

    public function hasAttendanceEvidence(string $registrationId): bool
    {
        if ($this->evidenceQuery()
            ->where('event_registration_id', $registrationId)
            ->exists()) {
            return true;
        }

        $participantIds = array_map(
            static fn (EventRegistrationParticipantData $participant): string => $participant->id,
            $this->registrations->participantsForRegistration($registrationId),
        );

        if ($participantIds === []) {
            return false;
        }

        return $this->evidenceQuery()
            ->whereIn('event_registration_participant_id', $participantIds)
            ->exists();
    }

    public function registrationIdForAttendance(string $attendanceId): ?string
    {
        $registrationId = EventAttendance::query()
            ->whereKey($attendanceId)
            ->value('event_registration_id');

        return is_scalar($registrationId) && (string) $registrationId !== '' ? (string) $registrationId : null;
    }

    /**
     * @return Builder<EventAttendance>
     */
    private function evidenceQuery(): Builder
    {
        return EventAttendance::query()
            ->whereNull('cancelled_at')
            ->where(function (Builder $query): void {
                $query
                    ->where('attendance_type', self::ATTENDANCE_ATTENDED)
                    ->orWhere(function (Builder $checkInQuery): void {
                        $checkInQuery
                            ->whereNotNull('checked_in_at')
                            ->where(function (Builder $typeQuery): void {
                                $typeQuery
                                    ->whereNull('attendance_type')
                                    ->orWhere('attendance_type', '!=', self::ATTENDANCE_DID_NOT_ATTEND);
                            });
                    });
            });
    }
}
