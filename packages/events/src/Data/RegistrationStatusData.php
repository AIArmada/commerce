<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\Registration;
use Spatie\LaravelData\Data;

final class RegistrationStatusData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $eventId,
        public readonly string $occurrenceId,
        public readonly string $status,
        public readonly string $label,
        public readonly string $color,
        public readonly bool $canCheckIn,
        public readonly bool $isTerminal,
        public readonly bool $isPaid = false,
        public readonly bool $approvalRequired = false,
        public readonly ?string $orderId = null,
        public readonly ?string $orderItemId = null,
        public readonly ?string $attendeeType = null,
        public readonly ?string $attendeeId = null,
        public readonly ?string $checkedInAt = null,
        public readonly ?string $cancelledAt = null,
        public readonly ?array $metadata = null,
    ) {}

    public static function fromRegistration(Registration $registration): self
    {
        $registration->loadMissing(['occurrence']);

        return new self(
            id: $registration->id,
            eventId: $registration->occurrence->event_id,
            occurrenceId: $registration->occurrence_id,
            status: $registration->status->value,
            label: $registration->status->label(),
            color: $registration->status->color(),
            canCheckIn: $registration->status->canCheckIn(),
            isTerminal: $registration->status->isTerminal(),
            isPaid: $registration->order_id !== null,
            approvalRequired: $registration->occurrence->approval_required,
            orderId: $registration->order_id,
            orderItemId: $registration->order_item_id,
            attendeeType: $registration->attendee_type,
            attendeeId: $registration->attendee_id,
            checkedInAt: $registration->checked_in_at?->toISOString(),
            cancelledAt: $registration->cancelled_at?->toISOString(),
            metadata: $registration->metadata,
        );
    }
}
