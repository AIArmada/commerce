<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Contracts\CommunicationContextAware;
use AIArmada\Communications\Contracts\ContentRenderer;
use AIArmada\Communications\Contracts\DestinationResolver;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Contracts\RecipientSnapshotResolver;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Enums\RecipientRole;
use AIArmada\Communications\Events\CommunicationCreated;
use AIArmada\Communications\Events\CommunicationQueued;
use AIArmada\Communications\Jobs\DispatchManagedNotificationJob;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationContent;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationRecipient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use RuntimeException;
use Spatie\LaravelData\Optional;

final class DispatchManagedNotificationAction
{
    public function __construct(
        private readonly RecipientSnapshotResolver $recipientResolver,
        private readonly DestinationResolver $destinationResolver,
        private readonly ContentRenderer $contentRenderer,
        private readonly ResolveCommunicationEligibilityAction $eligibility,
        private readonly PayloadRedactor $redactor,
    ) {}

    public function handle(
        mixed $notifiable,
        Notification $notification,
        CommunicationContextData $context,
    ): Communication {
        return $this->execute(
            $notifiable,
            $notification,
            $context,
            $this->normalizeIdempotencyKey($context),
        )['communication'];
    }

    public function handleUnique(
        mixed $notifiable,
        Notification $notification,
        CommunicationContextData $context,
    ): Communication {
        $idempotencyKey = $this->normalizeIdempotencyKey($context);
        $result = $this->execute($notifiable, $notification, $context, $idempotencyKey);

        if (! $result['created']) {
            throw new RuntimeException('Duplicate communication detected for idempotency key: ' . (string) $idempotencyKey);
        }

        return $result['communication'];
    }

    /**
     * @return array{communication: Communication, created: bool}
     */
    private function execute(
        mixed $notifiable,
        Notification $notification,
        CommunicationContextData $context,
        ?string $idempotencyKey,
    ): array {
        if (! is_object($notifiable) || ! method_exists($notifiable, 'getKey')) {
            throw new InvalidArgumentException('Notifiable must be an object exposing getKey().');
        }

        $notifiableType = $notifiable instanceof Model
            ? $notifiable->getMorphClass()
            : $notifiable::class;

        [$communication, $deliveryIdsByChannel, $created] = (new Communication)->getConnection()->transaction(function () use ($notifiable, $notifiableType, $notification, $context, $idempotencyKey): array {
            if ($idempotencyKey !== null) {
                $existing = Communication::query()
                    ->forOwner()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof Communication) {
                    return [$existing, [], false];
                }
            }

            $communication = new Communication;
            $communication->direction = $context->direction;
            $communication->category = $context->category;
            $communication->priority = $context->priority;
            $communication->purpose = $context->purpose;
            $communication->status = CommunicationStatus::Queued;
            $communication->idempotency_key = $idempotencyKey;
            $communication->locale = $context->locale;
            $communication->timezone = $context->timezone;
            $scheduledAt = $context->scheduledAt;
            $expiresAt = $context->expiresAt;
            $communication->scheduled_at = is_string($scheduledAt) ? CarbonImmutable::parse($scheduledAt) : null;
            $communication->expires_at = is_string($expiresAt) ? CarbonImmutable::parse($expiresAt) : null;
            $communication->metadata = $this->redactor->redact($context->metadata);
            $communication->subject_type = $context->subjectType;
            $communication->subject_id = $context->subjectId;
            $communication->sender_type = $context->senderType;
            $communication->sender_id = $context->senderId;
            $communication->batch_id = $context->batchId;
            $communication->thread_id = $context->threadId;
            $communication->parent_id = $context->parentId;
            $communication->queued_at = CarbonImmutable::now();

            try {
                $communication->getConnection()->transaction(static fn (): bool => $communication->save());
            } catch (QueryException $exception) {
                if ($idempotencyKey === null || ! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $existing = Communication::query()
                    ->forOwner()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof Communication) {
                    return [$existing, [], false];
                }

                throw $exception;
            }

            Event::dispatch(new CommunicationCreated(
                communicationId: $communication->id,
                category: $communication->category->value,
                direction: $communication->direction->value,
                purpose: $communication->purpose,
            ));

            $snapshot = $this->recipientResolver->resolve($notifiable);

            $recipient = new CommunicationRecipient;
            $recipient->communication_id = $communication->id;
            $recipient->recipient_type = $notifiableType;
            $recipient->recipient_id = $notifiable->getKey();
            $recipient->role = RecipientRole::To;
            $recipient->display_name = $snapshot->displayName;
            $recipient->locale = $snapshot->locale;
            $recipient->timezone = $snapshot->timezone;
            $recipient->snapshot = $this->redactor->redact($snapshot->extra);
            $recipient->save();

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

                $destination = $this->destinationResolver->resolve($notifiable, $channel);
                $destinationHash = $destination?->hash;

                $eligibility = $this->eligibility->handle(
                    recipientType: $notifiableType,
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
                $content->subject = $this->redactor->redactText($rendered->subject);
                $content->content_text = $this->redactor->redactText($rendered->contentText);
                $content->content_html = $this->redactor->redactText($rendered->contentHtml);
                $content->payload = $this->redactor->redact($rendered->payload);
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
                    $delivery->max_attempts = (int) config('communications.defaults.max_attempts', 3);
                    $delivery->queued_at = CarbonImmutable::now();
                    $delivery->save();
                    $deliveryIdsByChannel[$channel] = $delivery->id;
                }
            }

            Event::dispatch(new CommunicationQueued(
                communicationId: $communication->id,
            ));

            return [$communication, $deliveryIdsByChannel, true];
        });

        if ($created && $notification instanceof CommunicationContextAware) {
            $notification->withCommunicationContext(
                communicationId: $communication->id,
                deliveryIdsByChannel: $deliveryIdsByChannel,
                ownerType: $communication->owner_type,
                ownerId: $communication->owner_id,
            );
        }

        if ($created && $deliveryIdsByChannel !== []) {
            DispatchManagedNotificationJob::dispatch(
                notifiable: $notifiable,
                notification: $notification,
                channels: array_keys($deliveryIdsByChannel),
                ownerType: $communication->owner_type,
                ownerId: $communication->owner_id,
                ownerIsGlobal: $communication->owner_type === null && $communication->owner_id === null,
            )->afterCommit();
        }

        return ['communication' => $communication, 'created' => $created];
    }

    private function normalizeIdempotencyKey(CommunicationContextData $context): ?string
    {
        $key = $context->idempotencyKey;

        if ($key === null || $key instanceof Optional) {
            return null;
        }

        if (! is_string($key)) {
            throw new InvalidArgumentException('Communication idempotency key must be a string.');
        }

        $key = mb_trim($key);

        if ($key === '') {
            throw new InvalidArgumentException('Communication idempotency key must not be blank.');
        }

        return $key;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
