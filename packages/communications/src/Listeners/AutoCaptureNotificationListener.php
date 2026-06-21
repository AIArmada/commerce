<?php

declare(strict_types=1);

namespace AIArmada\Communications\Listeners;

use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Contracts\ContentRenderer;
use AIArmada\Communications\Contracts\DestinationResolver;
use AIArmada\Communications\Contracts\RecipientSnapshotResolver;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Enums\RecipientRole;
use AIArmada\Communications\Models\CommunicationContent;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationRecipient;
use AIArmada\Communications\Support\AutoCaptureState;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;

final class AutoCaptureNotificationListener
{
    public function __construct(
        private readonly CommunicationRecorder $recorder,
        private readonly DestinationResolver $destinationResolver,
        private readonly RecipientSnapshotResolver $recipientResolver,
        private readonly ContentRenderer $contentRenderer,
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
            $key = spl_object_id($event->notification);

            $existing = $this->state->get($key);

            if ($existing !== null) {
                $this->captureAdditionalChannel(
                    communicationId: $existing['communicationId'],
                    notifiable: $event->notifiable,
                    notification: $event->notification,
                    channel: $event->channel,
                    key: $key,
                );

                return;
            }

            $this->captureFirstChannel(
                notifiable: $event->notifiable,
                notification: $event->notification,
                channel: $event->channel,
                key: $key,
            );
        } finally {
            $this->state->leaveRecursion();
        }
    }

    public function handleSent(NotificationSent $event): void
    {
        if (! config('communications.features.auto_capture', false)) {
            return;
        }

        $key = spl_object_id($event->notification);
        $state = $this->state->get($key);

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
    }

    private function isAllowedNotification(mixed $notification): bool
    {
        $allowlist = config('communications.features.auto_capture_allowlist', []);
        $denylist = config('communications.features.auto_capture_denylist', []);

        $class = is_object($notification) ? $notification::class : gettype($notification);

        if ($denylist !== [] && in_array($class, $denylist, true)) {
            return false;
        }

        if ($allowlist !== [] && ! in_array($class, $allowlist, true)) {
            return false;
        }

        return true;
    }

    private function captureFirstChannel(
        mixed $notifiable,
        mixed $notification,
        string $channel,
        int $key,
    ): void {
        $communication = $this->recorder->createCommunication(
            CommunicationContextData::from([
                'direction' => 'outbound',
                'category' => 'transactional',
                'priority' => config('communications.defaults.priority', 'normal'),
            ]),
        );

        $snapshot = $this->recipientResolver->resolve($notifiable);

        $recipient = new CommunicationRecipient;
        $recipient->communication_id = $communication->id;
        $recipient->recipient_type = is_object($notifiable) ? $notifiable::class : null;
        $recipient->recipient_id = is_object($notifiable) && method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null;
        $recipient->role = RecipientRole::To;
        $recipient->display_name = $snapshot->displayName;
        $recipient->locale = $snapshot->locale;
        $recipient->timezone = $snapshot->timezone;
        $recipient->snapshot = $snapshot->extra;
        $recipient->save();

        $content = $this->createContent($communication->id, $recipient->id, $notifiable, $notification, $channel);

        $delivery = $this->createDelivery($communication->id, $recipient->id, $content->id, $notifiable, $channel);

        $this->state->register($key, $communication->id, [$channel => $delivery->id]);
    }

    private function captureAdditionalChannel(
        string $communicationId,
        mixed $notifiable,
        mixed $notification,
        string $channel,
        int $key,
    ): void {
        $recipient = CommunicationRecipient::query()
            ->where('communication_id', $communicationId)
            ->first();

        if ($recipient === null) {
            return;
        }

        $content = $this->createContent($communicationId, $recipient->id, $notifiable, $notification, $channel);

        $delivery = $this->createDelivery($communicationId, $recipient->id, $content->id, $notifiable, $channel);

        $this->state->addDelivery($key, $channel, $delivery->id);
    }

    private function createContent(
        string $communicationId,
        string $recipientId,
        mixed $notifiable,
        mixed $notification,
        string $channel,
    ): CommunicationContent {
        $rendered = $this->contentRenderer->renderFromNotification($notifiable, $notification, $channel);

        $content = new CommunicationContent;
        $content->communication_id = $communicationId;
        $content->recipient_id = $recipientId;
        $content->channel = $channel;
        $content->locale = $rendered->locale;
        $content->subject = $rendered->subject;
        $content->content_text = $rendered->contentText;
        $content->content_html = $rendered->contentHtml;
        $content->payload = $rendered->payload;
        $content->checksum = $rendered->checksum;
        $content->rendered_at = CarbonImmutable::now();
        $content->save();

        return $content;
    }

    private function createDelivery(
        string $communicationId,
        string $recipientId,
        string $contentId,
        mixed $notifiable,
        string $channel,
    ): CommunicationDelivery {
        $destination = $this->destinationResolver->resolve($notifiable, $channel);

        $delivery = new CommunicationDelivery;
        $delivery->communication_id = $communicationId;
        $delivery->recipient_id = $recipientId;
        $delivery->content_id = $contentId;
        $delivery->channel = $channel;
        $delivery->provider = $destination?->channel ?? $channel;
        $delivery->destination_ciphertext = $destination?->ciphertext;
        $delivery->destination_hash = $destination?->hash;
        $delivery->destination_hint = $destination?->hint;
        $delivery->status = DeliveryStatus::Queued;
        $delivery->attempt_count = 0;
        $delivery->max_attempts = config('communications.defaults.max_attempts', 3);
        $delivery->queued_at = CarbonImmutable::now();
        $delivery->save();

        return $delivery;
    }
}
