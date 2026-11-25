<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Queries;

use AIArmada\Events\Contracts\EventRefundEligibility;
use AIArmada\Events\Features\Attendance\Api\AttendanceApi;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

final class DefaultEventRefundEligibility implements EventRefundEligibility
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly RegistrationsApi $registrations,
        private readonly AttendanceApi $attendance,
    ) {}

    public function refundsEnabled(string $eventId): bool
    {
        $metadata = $this->catalog->eventMetadata($eventId);
        $configured = data_get($metadata, 'registration.refunds_enabled');

        return $configured === null
            ? (bool) config('events.features.commerce.refunds_enabled_by_default', false)
            : (bool) $configured;
    }

    public function canSelfServiceRefund(mixed $user, string $eventId, string $registrationId): bool
    {
        return $this->selfServiceRefundReason($user, $eventId, $registrationId) === null;
    }

    public function selfServiceRefundReason(mixed $user, string $eventId, string $registrationId): ?string
    {
        if (! $this->refundsEnabled($eventId)) {
            return 'refunds_disabled';
        }

        if (! $user instanceof Model) {
            return 'purchaser_only';
        }

        $summary = $this->registrations->summarize($registrationId);

        if ($summary === null) {
            return 'event_mismatch';
        }

        $matchesPurchaser = $summary->registrantType === $user->getMorphClass()
            && $summary->registrantId !== null
            && (string) $summary->registrantId === (string) $user->getKey();

        if (! $matchesPurchaser) {
            return 'purchaser_only';
        }

        if ($summary->eventId !== $eventId) {
            return 'event_mismatch';
        }

        if ($summary->status !== 'confirmed') {
            return 'admission_not_refundable';
        }

        if ($summary->totalAmount <= 0) {
            return 'free_admission';
        }

        if ($this->attendance->hasAttendanceEvidence($registrationId)) {
            return 'admission_already_used';
        }

        $deadline = $this->selfServiceRefundDeadline($eventId, $registrationId);

        if (! $deadline instanceof CarbonImmutable) {
            return 'refund_deadline_unavailable';
        }

        if (! now()->toImmutable()->isBefore($deadline)) {
            return 'refund_window_closed';
        }

        return null;
    }

    public function canOrganizerRefund(string $eventId, string $registrationId): bool
    {
        if (! $this->refundsEnabled($eventId)) {
            return false;
        }

        $summary = $this->registrations->summarize($registrationId);

        if ($summary === null || $summary->eventId !== $eventId) {
            return false;
        }

        if ($summary->totalAmount <= 0) {
            return false;
        }

        return in_array($summary->status, [
            'confirmed',
            'completed',
            'checked_in',
            'no_show',
        ], true);
    }

    public function selfServiceRefundDeadline(string $eventId, string $registrationId): ?CarbonImmutable
    {
        $startsAt = $this->admissionStartsAt($eventId, $registrationId);

        if (! $startsAt instanceof CarbonImmutable) {
            return null;
        }

        return $startsAt->subHours(max(0, (int) config(
            'events.features.commerce.refund_self_service_hours',
            48,
        )));
    }

    public function admissionStartsAt(string $eventId, string $registrationId): ?CarbonImmutable
    {
        $summary = $this->registrations->summarize($registrationId);

        $levels = [];

        if ($summary?->sessionId !== null) {
            $levels[] = ['session', $summary->sessionId];
        } elseif ($summary?->occurrenceId !== null) {
            $levels[] = ['occurrence', $summary->occurrenceId];
        }

        $levels[] = ['event', $eventId];

        foreach ($levels as [$scopeType, $scopeId]) {
            $startsAt = $this->catalog->describeScope($scopeType, $scopeId)?->startsAt;

            if ($startsAt instanceof CarbonImmutable) {
                return $startsAt;
            }
        }

        return null;
    }

    public function hasAttendanceEvidence(string $registrationId): bool
    {
        return $this->attendance->hasAttendanceEvidence($registrationId);
    }
}
