<?php

declare(strict_types=1);

use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationEvent;
use AIArmada\Communications\Models\CommunicationRecipient;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->communication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'event-test',
        'status' => CommunicationStatus::Draft,
    ]);
});

test('creates event with source type', function (): void {
    $event = CommunicationEvent::create([
        'communication_id' => $this->communication->id,
        'source' => CommunicationEventSource::System,
        'event' => 'communication.created',
        'occurred_at' => CarbonImmutable::now(),
        'received_at' => CarbonImmutable::now(),
    ]);

    expect($event->id)->toBeUuid();
    expect($event->source)->toBeInstanceOf(CommunicationEventSource::class);
    expect($event->source->value)->toBe('system');
    expect($event->event)->toBe('communication.created');
});

test('event can be associated with a delivery', function (): void {
    $recipient = CommunicationRecipient::create([
        'communication_id' => $this->communication->id,
        'role' => 'to',
    ]);

    $delivery = CommunicationDelivery::create([
        'communication_id' => $this->communication->id,
        'recipient_id' => $recipient->id,
        'channel' => 'mail',
        'provider' => 'array',
        'status' => DeliveryStatus::Pending,
        'attempt_count' => 0,
        'max_attempts' => 3,
    ]);

    $event = CommunicationEvent::create([
        'communication_id' => $this->communication->id,
        'delivery_id' => $delivery->id,
        'source' => CommunicationEventSource::Provider,
        'event' => 'delivery.sent',
        'occurred_at' => CarbonImmutable::now(),
        'received_at' => CarbonImmutable::now(),
    ]);

    expect($event->delivery)->toBeInstanceOf(CommunicationDelivery::class);
    expect($event->delivery->id)->toBe($delivery->id);
});

test('event stores provider payload', function (): void {
    $event = CommunicationEvent::create([
        'communication_id' => $this->communication->id,
        'source' => CommunicationEventSource::Provider,
        'event' => 'delivery.bounce',
        'provider_event_id' => 'evt_provider_123',
        'payload' => ['bounce_type' => 'permanent', 'code' => 550],
        'failure_message' => 'permanent bounce',
        'occurred_at' => CarbonImmutable::now(),
        'received_at' => CarbonImmutable::now(),
    ]);

    expect($event->provider_event_id)->toBe('evt_provider_123');
    expect($event->payload['bounce_type'])->toBe('permanent');
    expect($event->failure_message)->toBe('permanent bounce');
});
