<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\Communications\Contracts\CommunicationContextAware;
use AIArmada\Communications\Contracts\CommunicationCreator;
use AIArmada\Communications\Contracts\DestinationResolver;
use AIArmada\Communications\Contracts\NotificationContentCapture;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\EntryPoints\Jobs\DispatchManagedNotificationJob;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

final class DispatchManagedNotificationAction
{
    public function __construct(
        private readonly CommunicationCreator $creator,
        private readonly NotificationContentCapture $contentCapture,
        private readonly DestinationResolver $destinationResolver,
        private readonly ResolveCommunicationEligibilityAction $eligibility,
    ) {}

    public function handle(
        mixed $notifiable,
        Notification $notification,
        CommunicationContextData $context,
    ): Communication {
        if (! is_object($notifiable) || ! method_exists($notifiable, 'getKey')) {
            throw new InvalidArgumentException('Notifiable must be an object exposing getKey().');
        }

        $notifiableType = $notifiable instanceof Model
            ? $notifiable->getMorphClass()
            : $notifiable::class;

        $model = new Communication;
        [$communication, $deliveryIdsByChannel] = $model->getConnection()->transaction(function () use ($model, $notifiable, $notifiableType, $notification, $context): array {
            $communicationId = $this->creator->handle($context, queued: true);
            $communication = $model->newQuery()->findOrFail($communicationId);
            $this->contentCapture->recipient($communicationId, $notifiable);

            $channels = method_exists($notification, 'via')
                ? $notification->via($notifiable)
                : ['mail'];

            if (is_string($channels)) {
                $channels = [$channels];
            }

            if (! is_array($channels)) {
                throw new InvalidArgumentException('Notification channels must be a string or an array of strings.');
            }

            $deliveryIdsByChannel = [];

            foreach ($channels as $channel) {
                if (! is_string($channel) || mb_trim($channel) === '') {
                    throw new InvalidArgumentException('Notification channels must be non-empty strings.');
                }

                if (isset($deliveryIdsByChannel[$channel])) {
                    continue;
                }

                $destination = $this->destinationResolver->resolve($notifiable, $channel);
                $destinationHash = $destination?->hash;

                $eligibility = $this->eligibility->handle(
                    recipientType: $notifiableType,
                    recipientId: $notifiable->getKey(),
                    destinationHash: $destinationHash,
                    channel: $channel,
                    category: $context->category,
                );

                if (! $eligibility['eligible']) {
                    continue;
                }

                $content = $this->contentCapture->capture($communicationId, $notifiable, $notification, $channel);

                if ($destination !== null) {
                    $delivery = new CommunicationDelivery;
                    $delivery->communication_id = $communication->id;
                    $delivery->recipient_id = $content->recipientId;
                    $delivery->content_id = $content->contentId;
                    $delivery->channel = $channel;
                    $delivery->provider = $destination->channel;
                    $delivery->destination_ciphertext = $destination->ciphertext;
                    $delivery->destination_hash = $destination->hash;
                    $delivery->destination_hint = $destination->hint;
                    $delivery->status = DeliveryStatus::Queued;
                    $delivery->attempt_count = 0;
                    $delivery->max_attempts = (int) config('communications.defaults.max_attempts', 3);
                    $delivery->queued_at = CarbonImmutable::now();
                    $delivery->save();
                    $deliveryIdsByChannel[$channel] = $delivery->id;
                }
            }

            if ($notification instanceof CommunicationContextAware) {
                $notification->withCommunicationContext(
                    communicationId: $communication->id,
                    deliveryIdsByChannel: $deliveryIdsByChannel,
                    ownerType: $communication->owner_type,
                    ownerId: $communication->owner_id,
                );
            }

            return [$communication, $deliveryIdsByChannel];
        });

        if ($deliveryIdsByChannel !== []) {
            DispatchManagedNotificationJob::dispatch(
                notifiable: $notifiable,
                notification: $notification,
                channels: array_keys($deliveryIdsByChannel),
                ownerType: $communication->owner_type,
                ownerId: $communication->owner_id,
                ownerIsGlobal: $communication->owner_type === null && $communication->owner_id === null,
            )->afterCommit();
        }

        return $communication;
    }
}
