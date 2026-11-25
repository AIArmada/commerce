<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Features\Attendance\Api\AttendanceApi;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Events\Platform\CommerceIntegration;
use AIArmada\Events\Platform\EventWriteGuard;

final class SyncEventOrderCompletionAction
{
    public function __construct(
        private readonly AttendanceApi $attendance,
        private readonly RegistrationsApi $registrations,
        private readonly FulfillEventOrderItemAction $fulfillItem,
    ) {}

    public function handle(string $attendanceId): void
    {
        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            return;
        }

        $registrationId = $this->attendance->registrationIdForAttendance($attendanceId);

        if ($registrationId === null) {
            return;
        }

        $summary = $this->registrations->summarize($registrationId);

        if ($summary === null || $summary->externalOrderId === null) {
            return;
        }

        EventWriteGuard::findOrFail($summary->eventId);

        foreach ($this->registrations->orderItemsForRegistration($registrationId) as $item) {
            if ($item->externalOrderItemId !== null) {
                $this->fulfillItem->handle($item->id);
            }
        }
    }
}
