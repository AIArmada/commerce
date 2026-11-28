<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\Communications\Contracts\CommunicationCreator;
use AIArmada\Communications\Contracts\DestinationResolver;
use AIArmada\Communications\Contracts\NativeNotificationCapture;
use AIArmada\Communications\Contracts\NotificationContentCapture;
use AIArmada\Communications\Data\CapturedNotificationData;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;

final readonly class CaptureNativeNotification implements NativeNotificationCapture
{
    public function __construct(
        private CommunicationCreator $creator,
        private NotificationContentCapture $content,
        private DestinationResolver $destinations,
    ) {}

    public function capture(mixed $notifiable, object $notification, string $channel, ?string $communicationId = null): CapturedNotificationData
    {
        $destination = $this->destinations->resolve($notifiable, $channel);
        $delivery = new CommunicationDelivery;

        return $delivery->getConnection()->transaction(function () use ($delivery, $destination, $notifiable, $notification, $channel, $communicationId): CapturedNotificationData {
            $communicationId ??= $this->creator->handle(CommunicationContextData::from([
                'direction' => 'outbound',
                'category' => 'transactional',
                'priority' => config('communications.defaults.priority', 'normal'),
            ]));

            $captured = $this->content->capture($communicationId, $notifiable, $notification, $channel);
            $delivery->communication_id = $communicationId;
            $delivery->recipient_id = $captured->recipientId;
            $delivery->content_id = $captured->contentId;
            $delivery->channel = $channel;
            $delivery->provider = $destination?->channel ?? $channel;
            $delivery->destination_ciphertext = $destination?->ciphertext;
            $delivery->destination_hash = $destination?->hash;
            $delivery->destination_hint = $destination?->hint;
            $delivery->status = DeliveryStatus::Queued;
            $delivery->attempt_count = 0;
            $delivery->max_attempts = (int) config('communications.defaults.max_attempts', 3);
            $delivery->queued_at = CarbonImmutable::now();
            $delivery->save();

            return new CapturedNotificationData($communicationId, $delivery->id);
        });
    }
}
