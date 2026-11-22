<?php

declare(strict_types=1);

namespace AIArmada\Communications\Support;

use AIArmada\Communications\Actions\CompleteDeliveryAttemptAction;
use AIArmada\Communications\Actions\PersistDeliveryFencingAction;
use AIArmada\Communications\Actions\StartDeliveryAttemptAction;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationAttempt;
use AIArmada\Communications\Models\CommunicationDelivery;
use Illuminate\Notifications\Notification;

/**
 * Coordinates atomic delivery claiming, cancellation racing, and external
 * attempt fencing for context-bound notification channels.
 */
final class GuardedDeliverySend
{
    public static function deliveryIdFor(Notification $notification, string $channel): ?string
    {
        if (! method_exists($notification, 'deliveryIdForChannel')) {
            return null;
        }

        $id = $notification->deliveryIdForChannel($channel);

        return is_string($id) && $id !== '' ? $id : null;
    }

    public static function communicationIdOf(Notification $notification): ?string
    {
        if (! method_exists($notification, 'communicationId')) {
            return null;
        }

        $id = $notification->communicationId();

        return is_string($id) && $id !== '' ? $id : null;
    }

    public static function claim(string $deliveryId, ?string $communicationId): ?CommunicationDelivery
    {
        $delivery = CommunicationDelivery::query()->whereKey($deliveryId)->lockForUpdate()->first();

        if (! $delivery instanceof CommunicationDelivery) {
            return null;
        }

        $communication = $communicationId === null
            ? null
            : Communication::query()->whereKey($communicationId)->lockForUpdate()->first();

        if (! $communication instanceof Communication) {
            self::restoreSettledFlip($delivery);

            return null;
        }

        if ($communication->cancelled_at !== null || $delivery->cancelled_at !== null) {
            self::restoreCancel($delivery, $communication);

            return null;
        }

        if (! self::isSendable($communication)) {
            self::restoreSettledFlip($delivery);

            return null;
        }

        if ($delivery->status instanceof DeliveryStatus && in_array($delivery->status, [
            DeliveryStatus::Pending,
            DeliveryStatus::Scheduled,
            DeliveryStatus::Queued,
            DeliveryStatus::Sending,
        ], true)) {
            return $delivery;
        }

        self::restoreSettledFlip($delivery);

        return null;
    }

    public static function detach(Notification $notification, string $channel): void
    {
        if (property_exists($notification, 'deliveryIdsByChannel')
            && is_array($notification->deliveryIdsByChannel)) {
            unset($notification->deliveryIdsByChannel[$channel]);
        }
    }

    public static function claimExternal(string $deliveryId, ?string $communicationId): ?CommunicationDelivery
    {
        $delivery = self::claim($deliveryId, $communicationId);

        if (! $delivery instanceof CommunicationDelivery) {
            return null;
        }

        if (self::hasAnyAttempt($deliveryId)) {
            self::restoreSettledFlip($delivery);

            return null;
        }

        return $delivery;
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    public static function startExternalAttempt(
        string $deliveryId,
        CommunicationDelivery $delivery,
        array $operation = [],
    ): CommunicationAttempt {
        $frozen = [
            'delivery_id' => $deliveryId,
            'communication_id' => (string) $delivery->communication_id,
            'channel' => (string) $delivery->channel,
            'provider' => (string) $delivery->provider,
            'destination_hash' => $delivery->destination_hash,
            'content_id' => $delivery->content_id,
            'operation' => $operation,
        ];

        $attempt = app(StartDeliveryAttemptAction::class)->handle($deliveryId, [
            'channel' => (string) $delivery->channel,
            'destination_hash' => $delivery->destination_hash,
        ]);

        app(PersistDeliveryFencingAction::class)->recordFrozenAttempt($attempt, $frozen);

        return $attempt->refresh();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public static function recordExternalSent(
        string $communicationId,
        string $deliveryId,
        string $attemptId,
        array $result = [],
    ): bool {
        $delivery = CommunicationDelivery::query()->whereKey($deliveryId)->lockForUpdate()->first();
        $communication = Communication::query()->whereKey($communicationId)->lockForUpdate()->first();
        $attempt = CommunicationAttempt::query()->whereKey($attemptId)->lockForUpdate()->first();

        if (! $delivery instanceof CommunicationDelivery
            || ! $communication instanceof Communication
            || ! $attempt instanceof CommunicationAttempt
            || (string) $attempt->delivery_id !== $deliveryId
        ) {
            return false;
        }

        if ($communication->cancelled_at !== null || $delivery->cancelled_at !== null) {
            self::restoreCancel($delivery, $communication);

            return false;
        }

        if (self::isDeliveredStatus($delivery->status)) {
            if ($attempt->responded_at === null && $attempt->failed_at === null) {
                app(CompleteDeliveryAttemptAction::class)->handle(
                    $attemptId,
                    $result['provider_message_id'] ?? $delivery->provider_message_id,
                    null,
                    [],
                );
            }

            return true;
        }

        if ($delivery->status !== DeliveryStatus::Sending) {
            return false;
        }

        if (! self::attemptOwns($delivery, $attempt)) {
            return false;
        }

        if (! self::frozenMatches($delivery, $attempt)) {
            return false;
        }

        app(CompleteDeliveryAttemptAction::class)->handle(
            $attemptId,
            $result['provider_message_id'] ?? null,
            null,
            [],
        );

        app(PersistDeliveryFencingAction::class)->markSent($communicationId, $deliveryId, $result);

        return true;
    }

    public static function recordExternalFailure(
        string $communicationId,
        string $deliveryId,
        string $attemptId,
        string $failureMessage,
        ?string $failureCode = null,
    ): bool {
        $delivery = CommunicationDelivery::query()->whereKey($deliveryId)->lockForUpdate()->first();
        $communication = Communication::query()->whereKey($communicationId)->lockForUpdate()->first();
        $attempt = CommunicationAttempt::query()->whereKey($attemptId)->lockForUpdate()->first();

        if (! $delivery instanceof CommunicationDelivery
            || ! $communication instanceof Communication
            || ! $attempt instanceof CommunicationAttempt
            || (string) $attempt->delivery_id !== $deliveryId
        ) {
            return false;
        }

        if ($communication->cancelled_at !== null || $delivery->cancelled_at !== null) {
            self::restoreCancel($delivery, $communication);

            return false;
        }

        if (self::isDeliveredStatus($delivery->status)) {
            return false;
        }

        if (in_array($delivery->status, [
            DeliveryStatus::Failed,
            DeliveryStatus::Cancelled,
            DeliveryStatus::Expired,
            DeliveryStatus::Suppressed,
            DeliveryStatus::Unsubscribed,
            DeliveryStatus::Bounced,
            DeliveryStatus::Complained,
        ], true)) {
            return true;
        }

        if (! self::attemptOwns($delivery, $attempt)) {
            return false;
        }

        if (! self::frozenMatches($delivery, $attempt)) {
            return false;
        }

        if ($attempt->responded_at !== null || $attempt->failed_at !== null) {
            return false;
        }

        app(PersistDeliveryFencingAction::class)->recordFailure($attempt, $failureMessage, $failureCode);
        app(PersistDeliveryFencingAction::class)->markFailed($communicationId, $deliveryId, $failureMessage);

        return true;
    }

    public static function isAmbiguousExternal(string $deliveryId): bool
    {
        return CommunicationAttempt::query()
            ->where('delivery_id', $deliveryId)
            ->whereNotNull('started_at')
            ->whereNull('responded_at')
            ->whereNull('failed_at')
            ->exists();
    }

    public static function hasAnyAttempt(string $deliveryId): bool
    {
        return CommunicationAttempt::query()->where('delivery_id', $deliveryId)->exists();
    }

    private static function attemptOwns(CommunicationDelivery $delivery, CommunicationAttempt $attempt): bool
    {
        return (int) $delivery->attempt_count === (int) $attempt->attempt_number
            && (string) $attempt->delivery_id === (string) $delivery->getKey();
    }

    private static function frozenMatches(CommunicationDelivery $delivery, CommunicationAttempt $attempt): bool
    {
        $metadata = is_array($attempt->metadata) ? $attempt->metadata : [];
        $fence = $metadata[PersistDeliveryFencingAction::metadataKey()] ?? null;

        if (! is_array($fence) || ! is_array($fence['operation'] ?? null)) {
            return false;
        }

        foreach ([
            'delivery_id',
            'communication_id',
            'channel',
            'provider',
            'destination_hash',
            'content_id',
        ] as $key) {
            if (! array_key_exists($key, $fence)) {
                return false;
            }
        }

        return ($fence['delivery_id'] ?? null) === (string) $delivery->getKey()
            && ($fence['communication_id'] ?? null) === (string) $delivery->communication_id
            && ($fence['channel'] ?? null) === (string) $delivery->channel
            && ($fence['provider'] ?? null) === (string) $delivery->provider
            && ($fence['destination_hash'] ?? null) === $delivery->destination_hash
            && ($fence['content_id'] ?? null) === $delivery->content_id;
    }

    private static function isDeliveredStatus(mixed $status): bool
    {
        return $status instanceof DeliveryStatus && in_array($status, [
            DeliveryStatus::Sent,
            DeliveryStatus::Received,
            DeliveryStatus::Delivered,
            DeliveryStatus::Opened,
            DeliveryStatus::Read,
            DeliveryStatus::Clicked,
            DeliveryStatus::Replied,
        ], true);
    }

    private static function isSendable(Communication $communication): bool
    {
        return $communication->status instanceof CommunicationStatus
            && in_array($communication->status, [
                CommunicationStatus::Draft,
                CommunicationStatus::Scheduled,
                CommunicationStatus::Queued,
                CommunicationStatus::Processing,
                CommunicationStatus::PartiallyCompleted,
            ], true);
    }

    private static function restoreCancel(CommunicationDelivery $delivery, Communication $communication): void
    {
        app(PersistDeliveryFencingAction::class)->restoreCancelled($delivery, $communication);
    }

    private static function restoreSettledFlip(CommunicationDelivery $delivery): void
    {
        if ($delivery->status !== DeliveryStatus::Sending || $delivery->cancelled_at !== null) {
            return;
        }

        $restored = match (true) {
            $delivery->replied_at !== null => DeliveryStatus::Replied,
            $delivery->clicked_at !== null => DeliveryStatus::Clicked,
            $delivery->read_at !== null => DeliveryStatus::Read,
            $delivery->opened_at !== null => DeliveryStatus::Opened,
            $delivery->delivered_at !== null => DeliveryStatus::Delivered,
            $delivery->received_at !== null => DeliveryStatus::Received,
            $delivery->sent_at !== null => DeliveryStatus::Sent,
            $delivery->failed_at !== null => DeliveryStatus::Failed,
            $delivery->bounced_at !== null => DeliveryStatus::Bounced,
            $delivery->complained_at !== null => DeliveryStatus::Complained,
            $delivery->expired_at !== null => DeliveryStatus::Expired,
            $delivery->suppressed_at !== null => DeliveryStatus::Suppressed,
            $delivery->unsubscribed_at !== null => DeliveryStatus::Unsubscribed,
            default => null,
        };

        if ($restored instanceof DeliveryStatus) {
            app(PersistDeliveryFencingAction::class)->restoreSettledDelivery($delivery, $restored);
        }
    }
}
