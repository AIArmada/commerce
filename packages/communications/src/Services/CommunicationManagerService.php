<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Contracts\ContentRenderer;
use AIArmada\Communications\Contracts\IdempotencyLock;
use AIArmada\Communications\Contracts\RecipientSnapshotResolver;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Models\Communication;
use Illuminate\Notifications\Notification;
use RuntimeException;

class CommunicationManagerService implements CommunicationManager
{
    public function __construct(
        private readonly CommunicationRecorder $recorder,
        private readonly RecipientSnapshotResolver $recipientResolver,
        private readonly ContentRenderer $contentRenderer,
        private readonly IdempotencyLock $idempotencyLock,
    ) {}

    public function notify(
        mixed $notifiable,
        Notification $notification,
        ?CommunicationContextData $context = null,
    ): Communication {
        if ($context === null) {
            $context = CommunicationContextData::from([]);
        }

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

    public function recordNative(
        mixed $notifiable,
        Notification $notification,
        string $channel,
    ): ?Communication {
        return null;
    }
}
