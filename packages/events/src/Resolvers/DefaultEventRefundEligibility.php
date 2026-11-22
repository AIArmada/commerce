<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventRefundEligibility;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final readonly class DefaultEventRefundEligibility implements EventRefundEligibility
{
    private const string ATTENDANCE_ATTENDED = 'attended';

    private const string ATTENDANCE_DID_NOT_ATTEND = 'did_not_attend';

    public function refundsEnabled(Event $event): bool
    {
        $metadata = is_array($event->metadata) ? $event->metadata : [];
        $configured = data_get($metadata, 'registration.refunds_enabled');

        return $configured === null
            ? (bool) config('events.features.commerce.refunds_enabled_by_default', false)
            : (bool) $configured;
    }

    public function canSelfServiceRefund(?Model $user, Event $event, EventRegistration $registration): bool
    {
        return $this->selfServiceRefundReason($user, $event, $registration) === null;
    }

    public function selfServiceRefundReason(?Model $user, Event $event, EventRegistration $registration): ?string
    {
        if (! $this->refundsEnabled($event)) {
            return 'refunds_disabled';
        }

        if (! $user instanceof Model) {
            return 'purchaser_only';
        }

        $matchesPurchaser = $registration->getAttribute('registrant_type') === $user->getMorphClass()
            && (string) $registration->getAttribute('registrant_id') === (string) $user->getKey();

        if (! $matchesPurchaser) {
            return 'purchaser_only';
        }

        if ((string) $registration->event_id !== (string) $event->getKey()) {
            return 'event_mismatch';
        }

        if ($this->registrationStatus($registration) !== 'confirmed') {
            return 'admission_not_refundable';
        }

        if ((int) ($registration->total_amount ?? 0) <= 0) {
            return 'free_admission';
        }

        if ($this->hasAttendanceEvidence($registration)) {
            return 'admission_already_used';
        }

        $deadline = $this->selfServiceRefundDeadline($event, $registration);

        if (! $deadline instanceof CarbonImmutable) {
            return 'refund_deadline_unavailable';
        }

        if (! now()->toImmutable()->isBefore($deadline)) {
            return 'refund_window_closed';
        }

        return null;
    }

    public function canOrganizerRefund(Event $event, EventRegistration $registration): bool
    {
        if (! $this->refundsEnabled($event)) {
            return false;
        }

        if ((string) $registration->event_id !== (string) $event->getKey()) {
            return false;
        }

        if ((int) ($registration->total_amount ?? 0) <= 0) {
            return false;
        }

        return in_array($this->registrationStatus($registration), [
            'confirmed',
            'completed',
            'checked_in',
            'no_show',
        ], true);
    }

    public function selfServiceRefundDeadline(Event $event, EventRegistration $registration): ?CarbonImmutable
    {
        $startsAt = $this->admissionStartsAt($event, $registration);

        if (! $startsAt instanceof CarbonImmutable) {
            return null;
        }

        return $startsAt->subHours(max(0, (int) config(
            'events.features.commerce.refund_self_service_hours',
            48,
        )));
    }

    public function admissionStartsAt(Event $event, EventRegistration $registration): ?CarbonImmutable
    {
        $registration->loadMissing(['occurrence', 'session']);

        $session = $registration->getRelationValue('session');
        $occurrence = $registration->getRelationValue('occurrence');
        $startsAt = $session instanceof EventSession
            ? $session->starts_at
            : ($occurrence instanceof EventOccurrence ? $occurrence->starts_at : $event->getAttribute('starts_at'));

        return $this->toImmutable($startsAt);
    }

    public function hasAttendanceEvidence(EventRegistration $registration): bool
    {
        if ($registration->attendances()
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
            })
            ->exists()) {
            return true;
        }

        return $registration->participants()
            ->whereHas('attendances', function (Builder $query): void {
                $query
                    ->whereNull('cancelled_at')
                    ->where(function (Builder $attendanceQuery): void {
                        $attendanceQuery
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
            })
            ->exists();
    }

    private function registrationStatus(EventRegistration $registration): string
    {
        $status = $registration->getAttribute('status');

        if (is_string($status)) {
            return $status;
        }

        if (is_object($status) && method_exists($status, 'getValue')) {
            $value = $status->getValue();

            return is_string($value) ? $value : (string) $value;
        }

        return (string) $status;
    }

    private function toImmutable(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return null;
    }
}
