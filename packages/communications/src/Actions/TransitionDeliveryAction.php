<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

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
use AIArmada\Communications\Models\CommunicationDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use RuntimeException;

final class TransitionDeliveryAction
{
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['scheduled', 'queued', 'suppressed', 'cancelled'],
        'scheduled' => ['queued', 'cancelled'],
        'queued' => ['sending', 'cancelled', 'expired'],
        'sending' => ['accepted', 'sent', 'failed', 'cancelled'],
        'accepted' => ['sent', 'failed'],
        'sent' => ['received', 'delivered', 'bounced', 'complained', 'failed'],
        'received' => ['delivered', 'bounced'],
        'delivered' => ['opened', 'bounced', 'complained'],
        'opened' => ['read', 'clicked', 'replied'],
        'read' => ['clicked', 'replied'],
        'clicked' => ['replied'],
        'bounced' => ['suppressed'],
        'complained' => ['suppressed'],
        'suppressed' => [],
        'failed' => [],
        'cancelled' => [],
        'expired' => [],
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
    ];

    public function handle(
        CommunicationDelivery $delivery,
        DeliveryStatus $newStatus,
    ): CommunicationDelivery {
        $currentStatus = $delivery->status->value;
        $targetStatus = $newStatus->value;

        $allowed = self::ALLOWED_TRANSITIONS[$currentStatus];

        if (! in_array($targetStatus, $allowed, true)) {
            throw new RuntimeException(
                "Cannot transition delivery {$delivery->id} from {$currentStatus} to {$targetStatus}.",
            );
        }

        $delivery->status = $newStatus;

        $timestampColumn = self::STATUS_TIMESTAMP_MAP[$targetStatus];

        if ($delivery->{$timestampColumn} === null) {
            $delivery->{$timestampColumn} = CarbonImmutable::now();
        }

        $delivery->save();

        $this->dispatchEvent($delivery, $targetStatus);

        return $delivery;
    }

    private function dispatchEvent(CommunicationDelivery $delivery, string $targetStatus): void
    {
        $eventClass = self::STATUS_EVENT_MAP[$targetStatus] ?? null;

        if ($eventClass === null) {
            return;
        }

        if ($eventClass === DeliverySending::class) {
            Event::dispatch(new DeliverySending(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
                channel: $delivery->channel,
            ));
        } elseif ($eventClass === DeliveryAccepted::class) {
            Event::dispatch(new DeliveryAccepted(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
                providerMessageId: $delivery->provider_message_id ?? '',
            ));
        } elseif ($eventClass === DeliverySent::class) {
            Event::dispatch(new DeliverySent(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
                channel: $delivery->channel,
            ));
        } elseif ($eventClass === DeliveryDelivered::class) {
            Event::dispatch(new DeliveryDelivered(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
            ));
        } elseif ($eventClass === DeliveryRead::class) {
            Event::dispatch(new DeliveryRead(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
            ));
        } elseif ($eventClass === DeliveryClicked::class) {
            Event::dispatch(new DeliveryClicked(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
                targetUrl: $delivery->metadata['target_url'] ?? '',
            ));
        } elseif ($eventClass === DeliveryReplied::class) {
            Event::dispatch(new DeliveryReplied(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
            ));
        } elseif ($eventClass === DeliveryFailed::class) {
            Event::dispatch(new DeliveryFailed(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
                failureCode: $delivery->failure_code,
                failureMessage: $delivery->failure_message,
            ));
        } elseif ($eventClass === DeliverySuppressed::class) {
            Event::dispatch(new DeliverySuppressed(
                deliveryId: $delivery->id,
                communicationId: $delivery->communication_id,
                reason: 'suppressed_by_transition',
            ));
        }
    }
}
