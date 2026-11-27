<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\Communications\Features\Aggregate\Actions\AttachCommunicationReferenceAction;
use AIArmada\Communications\Features\Delivery\Actions\DispatchManagedNotificationAction;
use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Contracts\IdempotencyLock;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Support\EventReferenceNormalizer;
use Illuminate\Notifications\Notification;
use RuntimeException;
use Throwable;

class CommunicationManagerService implements CommunicationManager
{
    public function __construct(
        private readonly DispatchManagedNotificationAction $dispatcher,
        private readonly IdempotencyLock $idempotencyLock,
        private readonly AttachCommunicationReferenceAction $referenceAttacher,
        private readonly EventReferenceNormalizer $referenceNormalizer,
    ) {}

    public function notify(
        mixed $notifiable,
        Notification $notification,
        ?CommunicationContextData $context = null,
    ): string {
        $context ??= new CommunicationContextData;
        $eventReference = $context->subjectType !== null || $context->subjectId !== null
            ? $this->referenceNormalizer->normalize($context)
            : null;

        if ($context->idempotencyKey === null) {
            $communication = $this->dispatcher->handle($notifiable, $notification, $context);
            $this->attachEventReference($communication, $eventReference);

            return (string) $communication->getKey();
        }

        $acquired = $this->idempotencyLock->acquire(
            $context->idempotencyKey,
            (int) config('communications.cache.idempotency_ttl', 3600),
        );

        if (! $acquired) {
            throw new RuntimeException('Duplicate communication detected for idempotency key: ' . $context->idempotencyKey);
        }

        try {
            $communication = $this->dispatcher->handle($notifiable, $notification, $context);
            $this->attachEventReference($communication, $eventReference);
        } catch (Throwable $exception) {
            $this->idempotencyLock->release($context->idempotencyKey);

            throw $exception;
        }

        return (string) $communication->getKey();
    }

    /**
     * @param  array{type: string, id: string}|null  $eventReference
     */
    private function attachEventReference(Communication $communication, ?array $eventReference): void
    {
        if ($eventReference === null) {
            return;
        }

        $this->referenceAttacher->handle(
            communicationId: $communication->id,
            referenceType: $eventReference['type'],
            referenceId: $eventReference['id'],
            role: 'event',
        );
    }
}
