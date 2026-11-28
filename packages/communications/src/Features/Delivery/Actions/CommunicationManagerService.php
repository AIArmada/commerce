<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Contracts\CommunicationReferenceWriter;
use AIArmada\Communications\Contracts\IdempotencyLock;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Support\EventReferenceNormalizer;
use Illuminate\Notifications\Notification;
use RuntimeException;

final class CommunicationManagerService implements CommunicationManager
{
    public function __construct(
        private readonly DispatchManagedNotificationAction $dispatcher,
        private readonly IdempotencyLock $idempotencyLock,
        private readonly CommunicationReferenceWriter $referenceAttacher,
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

        $connection = (new Communication)->getConnection();

        return $connection->transaction(function () use ($connection, $notifiable, $notification, $context, $eventReference): string {
            if ($context->idempotencyKey !== null) {
                $lockKey = $this->scopedLockKey($context->idempotencyKey);
                $acquired = $this->idempotencyLock->acquire(
                    $lockKey,
                    max(1, (int) config('communications.cache.idempotency_ttl', 3600)),
                );

                if (! $acquired) {
                    throw new RuntimeException('Duplicate communication detected for idempotency key.');
                }

                $connection->afterRollBack(fn () => $this->idempotencyLock->release($lockKey));
            }

            $communication = $this->dispatcher->handle($notifiable, $notification, $context);
            $this->attachEventReference($communication, $eventReference);

            return (string) $communication->getKey();
        });
    }

    private function scopedLockKey(string $key): string
    {
        $config = Communication::ownerScopeConfig();
        $owner = $config->enabled ? OwnerContext::resolveConfigured($config) : null;

        if ($config->enabled) {
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
        }

        if (! $config->autoAssignOnCreate) {
            $owner = null;
        }

        return 'managed:' . hash('sha256', json_encode([
            $owner?->getMorphClass(),
            $owner === null ? null : (string) $owner->getKey(),
            $key,
        ], JSON_THROW_ON_ERROR));
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
