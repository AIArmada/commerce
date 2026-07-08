<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Contracts\ContentRenderer;
use AIArmada\Communications\Contracts\DestinationResolver;
use AIArmada\Communications\Contracts\RecipientSnapshotResolver;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Enums\RecipientRole;
use AIArmada\Communications\Events\CommunicationCreated;
use AIArmada\Communications\Events\CommunicationQueued;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationContent;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationRecipient;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class DispatchManagedNotificationAction
{
    public function __construct(
        private readonly RecipientSnapshotResolver $recipientResolver,
        private readonly DestinationResolver $destinationResolver,
        private readonly ContentRenderer $contentRenderer,
        private readonly ResolveCommunicationEligibilityAction $eligibility,
    ) {}

    public function handle(
        mixed $notifiable,
        Notification $notification,
        CommunicationContextData $context,
    ): Communication {
        return DB::transaction(function () use ($notifiable, $notification, $context) {
            $communication = new Communication;
            $communication->direction = $context->direction;
            $communication->category = $context->category;
            $communication->priority = $context->priority;
            $communication->purpose = $context->purpose;
            $communication->status = CommunicationStatus::Queued;
            $communication->idempotency_key = $context->idempotencyKey;
            $communication->locale = $context->locale;
            $communication->timezone = $context->timezone;
            $scheduledAt = $context->scheduledAt;
            $expiresAt = $context->expiresAt;
            $communication->scheduled_at = is_string($scheduledAt) ? CarbonImmutable::parse($scheduledAt) : null;
            $communication->expires_at = is_string($expiresAt) ? CarbonImmutable::parse($expiresAt) : null;
            $communication->metadata = $context->metadata;
            $communication->subject_type = $context->subjectType;
            $communication->subject_id = $context->subjectId;
            $communication->sender_type = $context->senderType;
            $communication->sender_id = $context->senderId;
            $communication->batch_id = $context->batchId;
            $communication->thread_id = $context->threadId;
            $communication->parent_id = $context->parentId;
            $communication->queued_at = CarbonImmutable::now();
            $communication->save();

            Event::dispatch(new CommunicationCreated(
                communicationId: $communication->id,
                category: $communication->category->value,
                direction: $communication->direction->value,
                purpose: $communication->purpose,
            ));

            $snapshot = $this->recipientResolver->resolve($notifiable);

            $recipient = new CommunicationRecipient;
            $recipient->communication_id = $communication->id;
            $recipient->recipient_type = $notifiable::class;
            $recipient->recipient_id = $notifiable->getKey();
            $recipient->role = RecipientRole::To;
            $recipient->display_name = $snapshot->displayName;
            $recipient->locale = $snapshot->locale;
            $recipient->timezone = $snapshot->timezone;
            $recipient->snapshot = $snapshot->extra;
            $recipient->save();

            $channels = method_exists($notification, 'via')
                ? $notification->via($notifiable)
                : ['mail'];

            foreach ($channels as $channel) {
                $destination = $this->destinationResolver->resolve($notifiable, $channel);
                $destinationHash = $destination?->hash;

                $eligibility = $this->eligibility->handle(
                    recipientType: $notifiable::class,
                    recipientId: $notifiable->getKey(),
                    destinationHash: $destinationHash,
                    channel: $channel,
                    category: $context->category->value,
                );

                if (! $eligibility['eligible']) {
                    continue;
                }

                $rendered = $this->contentRenderer->renderFromNotification($notifiable, $notification, $channel);

                $content = new CommunicationContent;
                $content->communication_id = $communication->id;
                $content->recipient_id = $recipient->id;
                $content->channel = $channel;
                $content->locale = $rendered->locale;
                $content->subject = $rendered->subject;
                $content->content_text = $rendered->contentText;
                $content->content_html = $rendered->contentHtml;
                $content->payload = $rendered->payload;
                $content->checksum = $rendered->checksum;
                $content->rendered_at = CarbonImmutable::now();
                $content->save();

                if ($destination !== null) {
                    $delivery = new CommunicationDelivery;
                    $delivery->communication_id = $communication->id;
                    $delivery->recipient_id = $recipient->id;
                    $delivery->content_id = $content->id;
                    $delivery->channel = $channel;
                    $delivery->provider = $destination->channel;
                    $delivery->destination_ciphertext = $destination->ciphertext;
                    $delivery->destination_hash = $destination->hash;
                    $delivery->destination_hint = $destination->hint;
                    $delivery->status = DeliveryStatus::Queued;
                    $delivery->attempt_count = 0;
                    $delivery->max_attempts = 3;
                    $delivery->queued_at = CarbonImmutable::now();
                    $delivery->save();
                }
            }

            $notifiable->notify($notification);

            Event::dispatch(new CommunicationQueued(
                communicationId: $communication->id,
            ));

            return $communication;
        });
    }
}
