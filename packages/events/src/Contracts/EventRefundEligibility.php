<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

interface EventRefundEligibility
{
    public function refundsEnabled(Event $event): bool;

    public function canSelfServiceRefund(?Model $user, Event $event, EventRegistration $registration): bool;

    public function selfServiceRefundReason(?Model $user, Event $event, EventRegistration $registration): ?string;

    public function canOrganizerRefund(Event $event, EventRegistration $registration): bool;

    public function selfServiceRefundDeadline(Event $event, EventRegistration $registration): ?CarbonImmutable;

    public function admissionStartsAt(Event $event, EventRegistration $registration): ?CarbonImmutable;

    public function hasAttendanceEvidence(EventRegistration $registration): bool;
}
