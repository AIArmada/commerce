<?php

declare(strict_types=1);

use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationAttempt;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationEvent;
use AIArmada\Communications\Models\CommunicationRecipient;
use AIArmada\Communications\Models\CommunicationTrackingToken;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->communication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'delivery-test',
        'status' => CommunicationStatus::Draft,
    ]);

    $this->recipient = CommunicationRecipient::create([
        'communication_id' => $this->communication->id,
        'role' => 'to',
    ]);

    $this->delivery = CommunicationDelivery::create([
        'communication_id' => $this->communication->id,
        'recipient_id' => $this->recipient->id,
        'channel' => 'mail',
        'provider' => 'array',
        'status' => DeliveryStatus::Pending,
        'attempt_count' => 0,
        'max_attempts' => 3,
    ]);
});

test('creates a delivery record', function (): void {
    expect($this->delivery->id)->toBeUuid();
    expect($this->delivery->communication_id)->toBe($this->communication->id);
    expect($this->delivery->channel)->toBe('mail');
    expect($this->delivery->status)->toBeInstanceOf(DeliveryStatus::class);
    expect($this->delivery->status->value)->toBe('pending');
    expect($this->delivery->attempt_count)->toBe(0);
    expect($this->delivery->max_attempts)->toBe(3);
});

test('belongs to communication and recipient', function (): void {
    expect($this->delivery->communication)->toBeInstanceOf(Communication::class);
    expect($this->delivery->communication->id)->toBe($this->communication->id);
    expect($this->delivery->recipient)->toBeInstanceOf(CommunicationRecipient::class);
    expect($this->delivery->recipient->id)->toBe($this->recipient->id);
});

test('has many attempts', function (): void {
    $attempt = CommunicationAttempt::create([
        'delivery_id' => $this->delivery->id,
        'attempt_number' => 1,
        'provider' => 'array',
    ]);

    expect($this->delivery->attempts)->toHaveCount(1);
    expect($this->delivery->attempts->first()->id)->toBe($attempt->id);
});

test('has many events', function (): void {
    $event = CommunicationEvent::create([
        'communication_id' => $this->communication->id,
        'delivery_id' => $this->delivery->id,
        'source' => CommunicationEventSource::System,
        'event' => 'delivery.sent',
        'occurred_at' => CarbonImmutable::now(),
        'received_at' => CarbonImmutable::now(),
    ]);

    expect($this->delivery->events)->toHaveCount(1);
    expect($this->delivery->events->first()->id)->toBe($event->id);
});

test('has many tracking tokens', function (): void {
    $token = CommunicationTrackingToken::create([
        'delivery_id' => $this->delivery->id,
        'kind' => 'open',
        'token_hash' => hash('sha256', 'tok_' . bin2hex(random_bytes(16))),
    ]);

    expect($this->delivery->trackingTokens)->toHaveCount(1);
    expect($this->delivery->trackingTokens->first()->id)->toBe($token->id);
});

test('delivery accepts status transitions', function (): void {
    $this->delivery->update(['status' => DeliveryStatus::Sending]);
    expect($this->delivery->fresh()->status->value)->toBe('sending');

    $this->delivery->update(['status' => DeliveryStatus::Sent]);
    expect($this->delivery->fresh()->status->value)->toBe('sent');

    $this->delivery->update(['status' => DeliveryStatus::Delivered]);
    expect($this->delivery->fresh()->status->value)->toBe('delivered');

    $this->delivery->update(['status' => DeliveryStatus::Failed, 'failure_message' => 'Bounce']);
    expect($this->delivery->fresh()->status->value)->toBe('failed');
    expect($this->delivery->fresh()->failure_message)->toBe('Bounce');
});

test('stores provider interaction metadata', function (): void {
    $this->delivery->update([
        'provider_message_id' => 'provider_msg_123',
        'provider_account_key' => 'acct_xyz',
        'cost_minor' => 50,
        'cost_currency' => 'USD',
        'metadata' => ['region' => 'us-east-1'],
    ]);

    $fresh = CommunicationDelivery::find($this->delivery->id);
    expect($fresh->provider_message_id)->toBe('provider_msg_123');
    expect($fresh->provider_account_key)->toBe('acct_xyz');
    expect($fresh->cost_minor)->toBe(50);
    expect($fresh->cost_currency)->toBe('USD');
    expect($fresh->metadata['region'])->toBe('us-east-1');
});

test('destination ciphertext fields work', function (): void {
    $this->delivery->update([
        'destination_ciphertext' => 'encrypted_data',
        'destination_hash' => 'abc123hash',
        'destination_hint' => 'user@***.com',
    ]);

    $fresh = CommunicationDelivery::find($this->delivery->id);
    expect($fresh->destination_ciphertext)->toBe('encrypted_data');
    expect($fresh->destination_hash)->toBe('abc123hash');
    expect($fresh->destination_hint)->toBe('user@***.com');
});
