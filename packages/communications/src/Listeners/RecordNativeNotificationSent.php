<?php

declare(strict_types=1);

namespace AIArmada\Communications\Listeners;

use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Traits\HasCommunicationContext;
use Illuminate\Notifications\Events\NotificationSent;

final class RecordNativeNotificationSent
{
    public function __construct(
        private readonly CommunicationRecorder $recorder,
    ) {}

    public function handle(NotificationSent $event): void
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
            $response = $event->response;
            $result = [];

            if (is_object($response) && method_exists($response, 'toArray')) {
                $result = $response->toArray();
            } elseif (is_array($response)) {
                $result = $response;
            } elseif (is_string($response)) {
                $result = ['provider_message_id' => $response];
            }

            $this->recorder->markSent(
                $communicationId,
                $deliveryId,
                $result,
            );
        }
    }
}
