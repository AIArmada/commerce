<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Data\ProviderEventData;
use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Features\Delivery\Api\ProviderEventProcessor;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Features\Delivery\Models\CommunicationEvent;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

final class ApplyProviderEventAction implements ProviderEventProcessor
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

    public function process(ProviderEventData $eventData): void
    {
        $this->handle($eventData);
    }

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
        return (new CommunicationDelivery)->getConnection()->transaction(function () use ($eventData, $eventType): CommunicationDelivery {
            $probe = $this->findDelivery($eventData->deliveryId);

            if ($eventData->communicationId !== null && $eventData->communicationId !== $probe->communication_id) {
                throw new RuntimeException('Provider event references a different communication.');
            }

            $communication = Communication::query()
                ->lockForUpdate()
                ->find($probe->communication_id);

            if (! $communication instanceof Communication) {
                throw new RuntimeException('Provider event delivery has no communication.');
            }

            if (Communication::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Communication::class, $communication->id);
            }

            $delivery = CommunicationDelivery::query()
                ->where('communication_id', $communication->id)
                ->lockForUpdate()
                ->findOrFail($probe->id);

            if (CommunicationDelivery::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(CommunicationDelivery::class, $delivery->id);
            }

            $event = $this->recordEvent($eventData, $delivery);

            $event->processed_at = CarbonImmutable::now();
            if (! $event->save()) {
                throw new RuntimeException('Provider event processing was cancelled.');
            }

            $this->transitions->applyProviderStatus($delivery, self::EVENT_STATUS_MAP[$eventType]);

            return $delivery->fresh() ?? $delivery;
        });
    }

    private function findDelivery(string $deliveryId): CommunicationDelivery
    {
        if (! CommunicationDelivery::ownerScopeConfig()->enabled) {
            return CommunicationDelivery::query()->findOrFail($deliveryId);
        }

        /** @var CommunicationDelivery $delivery */
        $delivery = OwnerWriteGuard::findOrFailForOwner(
            CommunicationDelivery::class,
            $deliveryId,
        );

        return $delivery;
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
            if (! $event->save()) {
                throw new RuntimeException('Provider event recording was cancelled.');
            }
        } catch (UniqueConstraintViolationException $exception) {
            throw new RuntimeException("Duplicate provider event {$data->provider}/{$providerEventId}.", previous: $exception);
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
