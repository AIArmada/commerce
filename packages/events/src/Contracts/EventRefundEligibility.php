<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use Carbon\CarbonImmutable;

interface EventRefundEligibility
{
    public function refundsEnabled(string $eventId): bool;

    public function canSelfServiceRefund(mixed $user, string $eventId, string $registrationId): bool;

    public function selfServiceRefundReason(mixed $user, string $eventId, string $registrationId): ?string;

    public function canOrganizerRefund(string $eventId, string $registrationId): bool;

    public function selfServiceRefundDeadline(string $eventId, string $registrationId): ?CarbonImmutable;

    public function admissionStartsAt(string $eventId, string $registrationId): ?CarbonImmutable;

    public function hasAttendanceEvidence(string $registrationId): bool;
}
