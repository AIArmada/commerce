<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Data\ProviderEventData;
use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ApplyProviderEventAction
{
    public function __construct(
        private readonly PayloadRedactor $redactor,
        private readonly TransitionDeliveryAction $transitions,
    ) {}

    public const EVENT_STATUS_MAP = [
        'bounce' => DeliveryStatus::Bounced,
        'complaint' => DeliveryStatus::Complained,
        'delivery' => DeliveryStatus::Delivered,
        'open' => DeliveryStatus::Opened,
        'read' => DeliveryStatus::Read,
        'click' => DeliveryStatus::Clicked,
        'send' => DeliveryStatus::Sent,
        'reject' => DeliveryStatus::Failed,
        'failed' => DeliveryStatus::Failed,
        'accept' => DeliveryStatus::Accepted,
        'suppress' => DeliveryStatus::Suppressed,
        'unsubscribe' => DeliveryStatus::Unsubscribed,
    ];

    public const DELIVERY_EVENTS = [
        'bounce', 'complaint', 'delivery', 'open', 'read', 'click',
        'send', 'reject', 'failed', 'accept', 'suppress', 'unsubscribe',
    ];

    public function handle(ProviderEventData $eventData): CommunicationDelivery
    {
        if ($eventData->deliveryId === null) {
            throw new RuntimeException('Provider event has no associated delivery.');
        }

        $eventType = $eventData->eventType;

        if (! in_array($eventType, self::DELIVERY_EVENTS, true)) {
            throw new RuntimeException("Unknown provider event type: {$eventType}.");
        }

        if (OwnerContext::resolve() === null && CommunicationDelivery::ownerScopeConfig()->enabled) {
            $owner = $this->resolveDeliveryOwner($eventData->deliveryId);

            return OwnerContext::withOwner($owner, fn (): CommunicationDelivery => $this->apply($eventData, $eventType));
        }

        return $this->apply($eventData, $eventType);
    }

    private function apply(ProviderEventData $eventData, string $eventType): CommunicationDelivery
    {
        return DB::transaction(function () use ($eventData, $eventType): CommunicationDelivery {
            $delivery = $this->findDeliveryForUpdate($eventData->deliveryId);

            $event = $this->recordEvent($eventData, $delivery);

            $this->transitions->applyProviderStatus($delivery, self::EVENT_STATUS_MAP[$eventType]);

            $event->processed_at = CarbonImmutable::now();
            $event->save();

            return $delivery->fresh() ?? $delivery;
        });
    }

    private function findDeliveryForUpdate(string $deliveryId): CommunicationDelivery
    {
        if (! CommunicationDelivery::ownerScopeConfig()->enabled) {
            return CommunicationDelivery::query()->lockForUpdate()->findOrFail($deliveryId);
        }

        /** @var CommunicationDelivery $delivery */
        $delivery = OwnerWriteGuard::findOrFailForOwner(
            CommunicationDelivery::class,
            $deliveryId,
        );

        return CommunicationDelivery::query()
            ->whereKey($delivery->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function resolveDeliveryOwner(string $deliveryId): ?Model
    {
        $delivery = CommunicationDelivery::query()
            ->withoutOwnerScope()
            ->find($deliveryId);

        if ($delivery === null) {
            throw new RuntimeException("Provider event references unknown delivery {$deliveryId}.");
        }

        return OwnerContext::fromTypeAndId($delivery->owner_type, $delivery->owner_id);
    }

    private function recordEvent(
        ProviderEventData $data,
        CommunicationDelivery $delivery,
    ): CommunicationEvent {
        $providerEventId = $data->providerEventId ?? $this->payloadEventId($data);
        $exists = CommunicationEvent::query()
            ->where('provider', $data->provider)
            ->where('provider_event_id', $providerEventId)
            ->exists();

        if ($exists) {
            throw new RuntimeException("Duplicate provider event {$data->provider}/{$providerEventId}.");
        }

        $event = new CommunicationEvent;
        $event->communication_id = $data->communicationId ?? $delivery->communication_id;
        $event->delivery_id = $delivery->id;
        $event->event = $data->eventType;
        $event->source = CommunicationEventSource::Provider;
        $event->provider = $data->provider;
        $event->provider_event_id = $providerEventId;
        $event->provider_message_id = $data->providerMessageId;
        $event->occurred_at = $data->occurredAt;
        $event->received_at = CarbonImmutable::now();
        $event->signature_validated_at = $data->signatureValidatedAt;
        $event->payload = $this->redactor->redact($data->payload);
        $event->failure_message = $data->failureMessage;

        try {
            $event->save();
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                throw new RuntimeException("Duplicate provider event {$data->provider}/{$providerEventId}.");
            }

            throw $exception;
        }

        return $event;
    }

    private function payloadEventId(ProviderEventData $data): string
    {
        $payload = $this->canonicalize($data->payload);

        return 'payload:' . hash('sha256', json_encode([
            'provider' => $data->provider,
            'event' => $data->eventType,
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
