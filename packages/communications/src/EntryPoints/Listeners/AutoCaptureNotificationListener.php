<?php

declare(strict_types=1);

namespace AIArmada\Communications\EntryPoints\Listeners;

use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Contracts\NativeNotificationCapture;
use AIArmada\Communications\Enums\NotificationFamily;
use AIArmada\Communications\Enums\NotificationTrigger;
use AIArmada\Communications\Support\AutoCaptureState;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;

final class AutoCaptureNotificationListener
{
    public function __construct(
        private readonly CommunicationRecorder $recorder,
        private readonly NativeNotificationCapture $capture,
        private readonly AutoCaptureState $state,
    ) {}

    public function handleSending(NotificationSending $event): void
    {
        if (! config('communications.features.auto_capture', false)) {
            return;
        }

        if ($this->state->isInsideRecursion()) {
            return;
        }

        if (property_exists($event->notification, 'communicationId') && $event->notification->communicationId !== null) {
            return;
        }

        if (! $this->isAllowedNotification($event->notification)) {
            return;
        }

        if (in_array($event->channel, config('communications.features.auto_capture_ignored_channels', []), true)) {
            return;
        }

        $this->state->enterRecursion();

        try {
            $existing = $this->state->get($event->notification, $event->notifiable);

            $captured = $this->capture->capture(
                notifiable: $event->notifiable,
                notification: $event->notification,
                channel: $event->channel,
                communicationId: $existing['communicationId'] ?? null,
            );

            if ($existing === null) {
                $this->state->register($event->notification, $event->notifiable, $captured->communicationId, [$event->channel => $captured->deliveryId]);
            } else {
                $this->state->addDelivery($event->notification, $event->notifiable, $event->channel, $captured->deliveryId);
            }
        } finally {
            $this->state->leaveRecursion();
        }
    }

    public function handleSent(NotificationSent $event): void
    {
        if (! config('communications.features.auto_capture', false)) {
            return;
        }

        $state = $this->state->get($event->notification, $event->notifiable);

        if ($state === null) {
            return;
        }

        $deliveryId = $state['deliveryIdsByChannel'][$event->channel] ?? null;

        if ($deliveryId === null) {
            return;
        }

        $result = [];
        $response = $event->response;

        if (is_object($response) && method_exists($response, 'toArray')) {
            $result = $response->toArray();
        } elseif (is_array($response)) {
            $result = $response;
        } elseif (is_string($response)) {
            $result = ['provider_message_id' => $response];
        }

        $this->recorder->markSent($state['communicationId'], $deliveryId, $result);
        $this->state->removeDelivery($event->notification, $event->notifiable, $event->channel);
    }

    private function isAllowedNotification(mixed $notification): bool
    {
        $allowlist = config('communications.features.auto_capture_allowlist', []);
        $denylist = config('communications.features.auto_capture_denylist', []);

        $class = is_object($notification) ? $notification::class : gettype($notification);

        if ($denylist !== [] && in_array($class, $denylist, true)) {
            return false;
        }

        if (in_array($class, $allowlist, true)) {
            return true;
        }

        $family = method_exists($notification, 'notificationFamily')
            ? $notification->notificationFamily()
            : null;
        $trigger = method_exists($notification, 'notificationTrigger')
            ? $notification->notificationTrigger()
            : null;

        if (! $family instanceof NotificationFamily || ! $trigger instanceof NotificationTrigger) {
            return false;
        }

        return $this->isAllowedEnum(
            $family->value,
            config('communications.features.auto_capture_families', []),
        ) && $this->isAllowedEnum(
            $trigger->value,
            config('communications.features.auto_capture_triggers', []),
        );
    }

    private function isAllowedEnum(string $value, mixed $allowlist): bool
    {
        if (! is_array($allowlist) || $allowlist === []) {
            return true;
        }

        foreach ($allowlist as $allowed) {
            if ($allowed instanceof NotificationFamily || $allowed instanceof NotificationTrigger) {
                if ($allowed->value === $value) {
                    return true;
                }

                continue;
            }

            if (is_string($allowed) && $allowed === $value) {
                return true;
            }
        }

        return false;
    }
}
