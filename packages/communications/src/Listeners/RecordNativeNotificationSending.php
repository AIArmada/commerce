<?php

declare(strict_types=1);

namespace AIArmada\Communications\Listeners;

use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Traits\HasCommunicationContext;
use Illuminate\Notifications\Events\NotificationSending;

final class RecordNativeNotificationSending
{
    public function __construct(
        private readonly CommunicationRecorder $recorder,
    ) {}

    public function handle(NotificationSending $event): void
    {
        $notification = $event->notification;

        if (! in_array(HasCommunicationContext::class, class_uses_recursive($notification), true)) {
            return;
        }

        $communicationId = $notification->communicationId ?? null;

        if ($communicationId === null) {
            return;
        }

        $deliveryIdsByChannel = $notification->deliveryIdsByChannel ?? [];
        $deliveryId = $deliveryIdsByChannel[$event->channel] ?? null;

        if ($deliveryId !== null) {
            $this->recorder->markSending($communicationId, $deliveryId);
        }
    }
}
