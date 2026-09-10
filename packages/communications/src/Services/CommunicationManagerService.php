<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\Communications\Actions\AttachCommunicationReferenceAction;
use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Contracts\ContentRenderer;
use AIArmada\Communications\Contracts\IdempotencyLock;
use AIArmada\Communications\Contracts\RecipientSnapshotResolver;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Support\EventReferenceNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use RuntimeException;

class CommunicationManagerService implements CommunicationManager
{
    public function __construct(
        private readonly CommunicationRecorder $recorder,
        private readonly RecipientSnapshotResolver $recipientResolver,
        private readonly ContentRenderer $contentRenderer,
        private readonly IdempotencyLock $idempotencyLock,
        private readonly AttachCommunicationReferenceAction $referenceAttacher,
        private readonly EventReferenceNormalizer $referenceNormalizer,
    ) {}

    public function notify(
        mixed $notifiable,
        Notification $notification,
        CommunicationContextData | Model | array | null $context = null,
    ): Communication {
        [$context, $eventReference] = $this->normalizeContext($context);

        if ($context->idempotencyKey !== null) {
            if ($this->idempotencyLock->exists($context->idempotencyKey)) {
                throw new RuntimeException('Duplicate communication detected for idempotency key: ' . $context->idempotencyKey);
            }

            $this->idempotencyLock->acquire(
                $context->idempotencyKey,
                config('communications.cache.idempotency_ttl', 3600),
            );
        }

        $communication = $this->recorder->createCommunication($context);

        if ($eventReference !== null) {
            $this->referenceAttacher->handle(
                communicationId: $communication->id,
                referenceType: $eventReference['type'],
                referenceId: $eventReference['id'],
                role: 'event',
            );
        }

        $recipient = $this->recipientResolver->resolve($notifiable);

        $channels = method_exists($notification, 'via')
            ? $notification->via($notifiable)
            : ['mail'];

        foreach ($channels as $channel) {
            $content = $this->contentRenderer->renderFromNotification($notifiable, $notification, $channel);
        }

        $notifiable->notify($notification);

        return $communication;
    }

    /**
     * @return array{0: CommunicationContextData, 1: array{type: string, id: string}|null}
     */
    private function normalizeContext(
        CommunicationContextData | Model | array | null $context,
    ): array {
        if ($context instanceof CommunicationContextData) {
            if (is_string($context->subjectType) && is_string($context->subjectId)) {
                return [$context, $this->referenceNormalizer->normalize($context)];
            }

            return [$context, null];
        }

        if ($context instanceof Model) {
            $reference = $this->referenceNormalizer->normalize($context);

            return [
                CommunicationContextData::from([
                    'subjectType' => $reference['type'],
                    'subjectId' => $reference['id'],
                    'purpose' => 'event',
                ]),
                $reference,
            ];
        }

        if (is_array($context)) {
            $event = $context['event'] ?? $context['reference'] ?? null;

            if ($event instanceof Model || is_array($event)) {
                $reference = $this->referenceNormalizer->normalize($event);
                $context['subjectType'] ??= $reference['type'];
                $context['subjectId'] ??= $reference['id'];
                unset($context['event'], $context['reference']);

                return [CommunicationContextData::from($context), $reference];
            }

            if (is_string($event) && array_key_exists('event_id', $context)) {
                $reference = $this->referenceNormalizer->normalize($event, $context['event_id']);
                $context['subjectType'] ??= $reference['type'];
                $context['subjectId'] ??= $reference['id'];
                unset($context['event'], $context['event_id']);

                return [CommunicationContextData::from($context), $reference];
            }

            if (
                array_key_exists('event_type', $context)
                || array_key_exists('reference_type', $context)
                || array_key_exists('subject_type', $context)
            ) {
                $reference = $this->referenceNormalizer->normalize($context);
                $context['subjectType'] ??= $reference['type'];
                $context['subjectId'] ??= $reference['id'];
                unset(
                    $context['event_type'],
                    $context['event_id'],
                    $context['reference_type'],
                    $context['reference_id'],
                    $context['subject_type'],
                    $context['subject_id'],
                );

                return [CommunicationContextData::from($context), $reference];
            }

            return [CommunicationContextData::from($context), null];
        }

        return [
            CommunicationContextData::from([]),
            null,
        ];
    }

    public function recordNative(
        mixed $notifiable,
        Notification $notification,
        string $channel,
    ): ?Communication {
        return null;
    }
}
