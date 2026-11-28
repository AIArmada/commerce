<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Events\DeliveryAccepted;
use AIArmada\Communications\Events\DeliveryClicked;
use AIArmada\Communications\Events\DeliveryDelivered;
use AIArmada\Communications\Events\DeliveryFailed;
use AIArmada\Communications\Events\DeliveryRead;
use AIArmada\Communications\Events\DeliveryReplied;
use AIArmada\Communications\Events\DeliverySending;
use AIArmada\Communications\Events\DeliverySent;
use AIArmada\Communications\Events\DeliverySuppressed;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RuntimeException;

final class TransitionDeliveryAction
{
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['scheduled', 'queued', 'suppressed', 'unsubscribed', 'cancelled'],
        'scheduled' => ['queued', 'unsubscribed', 'cancelled'],
        'queued' => ['sending', 'unsubscribed', 'cancelled', 'expired'],
        'sending' => ['accepted', 'sent', 'unsubscribed', 'failed', 'cancelled'],
        'accepted' => ['sent', 'unsubscribed', 'failed'],
        'sent' => ['received', 'delivered', 'unsubscribed', 'bounced', 'complained', 'failed'],
        'received' => ['delivered', 'unsubscribed', 'bounced'],
        'delivered' => ['opened', 'unsubscribed', 'bounced', 'complained'],
        'opened' => ['read', 'clicked', 'unsubscribed', 'replied'],
        'read' => ['clicked', 'unsubscribed', 'replied'],
        'clicked' => ['unsubscribed', 'replied'],
        'bounced' => ['suppressed', 'unsubscribed'],
        'complained' => ['suppressed', 'unsubscribed'],
        'suppressed' => [],
        'failed' => [],
        'cancelled' => [],
        'expired' => [],
        'unsubscribed' => [],
    ];

    private const STATUS_EVENT_MAP = [
        'sending' => DeliverySending::class,
        'accepted' => DeliveryAccepted::class,
        'sent' => DeliverySent::class,
        'delivered' => DeliveryDelivered::class,
        'read' => DeliveryRead::class,
        'clicked' => DeliveryClicked::class,
        'replied' => DeliveryReplied::class,
        'failed' => DeliveryFailed::class,
        'suppressed' => DeliverySuppressed::class,
    ];

    private const STATUS_TIMESTAMP_MAP = [
        'scheduled' => 'scheduled_at',
        'queued' => 'queued_at',
        'sending' => 'sending_at',
        'accepted' => 'accepted_at',
        'sent' => 'sent_at',
        'received' => 'received_at',
        'delivered' => 'delivered_at',
        'opened' => 'opened_at',
        'read' => 'read_at',
        'clicked' => 'clicked_at',
        'replied' => 'replied_at',
        'bounced' => 'bounced_at',
        'complained' => 'complained_at',
        'failed' => 'failed_at',
        'cancelled' => 'cancelled_at',
        'expired' => 'expired_at',
        'suppressed' => 'suppressed_at',
        'unsubscribed' => 'unsubscribed_at',
    ];

    public const TERMINAL_STATUSES = ['failed', 'cancelled', 'expired', 'suppressed', 'unsubscribed'];

    private const PROGRESS_PRECEDENCE = [
        'pending' => 0,
        'scheduled' => 1,
        'queued' => 2,
        'sending' => 3,
        'accepted' => 4,
        'sent' => 5,
        'received' => 6,
        'delivered' => 7,
        'opened' => 8,
        'read' => 9,
        'clicked' => 10,
        'replied' => 11,
    ];

    private const NEGATIVE_STATUSES = [
        'failed',
        'bounced',
        'complained',
        'cancelled',
        'expired',
        'suppressed',
        'unsubscribed',
    ];

    public function handle(
        CommunicationDelivery $delivery,
        DeliveryStatus $newStatus,
        bool $force = false,
    ): CommunicationDelivery {
        $currentStatus = $delivery->status->value;
        $targetStatus = $newStatus->value;

        if (! $force) {
            $allowed = self::ALLOWED_TRANSITIONS[$currentStatus];

            if (! in_array($targetStatus, $allowed, true)) {
                throw new RuntimeException(
                    "Cannot transition delivery {$delivery->id} from {$currentStatus} to {$targetStatus}.",
                );
            }
        }

        $delivery->status = $newStatus;

        $timestampColumn = self::STATUS_TIMESTAMP_MAP[$targetStatus];

        if ($delivery->{$timestampColumn} === null) {
            $delivery->{$timestampColumn} = CarbonImmutable::now();
        }

        if (! $delivery->save()) {
            throw new RuntimeException('Delivery transition was cancelled.');
        }

        $this->dispatchEvent($delivery, $targetStatus);

        return $delivery;
    }

    /**
     * Apply a provider-reported status as an explicit out-of-order-tolerant jump.
     *
     * Returns false — leaving the delivery untouched — when the delivery is
     * already terminal and the late provider event must not regress it.
     */
    public function applyProviderStatus(
        CommunicationDelivery $delivery,
        DeliveryStatus $target,
    ): bool {
        $current = $delivery->status;

        if ($current === $target) {
            $this->stampIfMissing($delivery, $target);

            return true;
        }

        if (in_array($current->value, self::TERMINAL_STATUSES, true)) {
            return false;
        }

        $targetIsNegative = in_array($target->value, self::NEGATIVE_STATUSES, true);
        $currentIsNegative = in_array($current->value, self::NEGATIVE_STATUSES, true);

        if (! $targetIsNegative) {
            if ($currentIsNegative) {
                if ($this->progressRank($target) > self::PROGRESS_PRECEDENCE['sent']) {
                    return false;
                }

                $this->stampIfMissing($delivery, $target);

                return true;
            }

            if ($this->progressRank($target) > $this->progressRank($current)) {
                $this->handle($delivery, $target, force: true);

                return true;
            }

            $this->stampIfMissing($delivery, $target);

            return true;
        }

        $allowed = self::ALLOWED_TRANSITIONS[$current->value] ?? [];

        if (! in_array($target->value, $allowed, true)
            && ($currentIsNegative || $this->progressRank($current) > self::PROGRESS_PRECEDENCE['sent'])) {
            return false;
        }

        $this->handle($delivery, $target, force: true);

        return true;
    }

    private function stampIfMissing(CommunicationDelivery $delivery, DeliveryStatus $status): void
    {
        $timestampColumn = self::STATUS_TIMESTAMP_MAP[$status->value];

        if ($delivery->{$timestampColumn} !== null) {
            return;
        }

        $delivery->{$timestampColumn} = CarbonImmutable::now();

        if (! $delivery->save()) {
            throw new RuntimeException('Delivery transition was cancelled.');
        }
    }

    private function progressRank(DeliveryStatus $status): int
    {
        return self::PROGRESS_PRECEDENCE[$status->value] ?? -1;
    }

    private function dispatchEvent(CommunicationDelivery $delivery, string $targetStatus): void
    {
        $eventClass = self::STATUS_EVENT_MAP[$targetStatus] ?? null;

        if ($eventClass === null) {
            return;
        }

        $event = null;

        if ($eventClass === DeliverySending::class) {
            $event = new DeliverySending(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
                channel: $delivery->channel,
            );
        } elseif ($eventClass === DeliveryAccepted::class) {
            $event = new DeliveryAccepted(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
                providerMessageId: $delivery->provider_message_id ?? '',
            );
        } elseif ($eventClass === DeliverySent::class) {
            $event = new DeliverySent(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
                channel: $delivery->channel,
            );
        } elseif ($eventClass === DeliveryDelivered::class) {
            $event = new DeliveryDelivered(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
            );
        } elseif ($eventClass === DeliveryRead::class) {
            $event = new DeliveryRead(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
            );
        } elseif ($eventClass === DeliveryClicked::class) {
            $event = new DeliveryClicked(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
                targetUrl: $delivery->metadata['target_url'] ?? '',
            );
        } elseif ($eventClass === DeliveryReplied::class) {
            $event = new DeliveryReplied(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
            );
        } elseif ($eventClass === DeliveryFailed::class) {
            $event = new DeliveryFailed(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
                failureCode: $delivery->failure_code,
                failureMessage: $delivery->failure_message,
            );
        } elseif ($eventClass === DeliverySuppressed::class) {
            $event = new DeliverySuppressed(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
                reason: 'suppressed_by_transition',
            );
        }

        $owner = $delivery->owner_type !== null || $delivery->owner_id !== null
            ? OwnerContext::fromTypeAndId($delivery->owner_type, $delivery->owner_id)
            : null;

        $delivery->getConnection()->afterCommit(function () use ($owner, $event): void {
            if (CommunicationDelivery::ownerScopeConfig()->enabled) {
                OwnerContext::withOwner($owner, fn () => Event::dispatch($event));

                return;
            }

            Event::dispatch($event);
        });
    }
}
