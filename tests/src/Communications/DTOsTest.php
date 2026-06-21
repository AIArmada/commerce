<?php

declare(strict_types=1);

use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Data\CommunicationDefinitionData;
use AIArmada\Communications\Data\ConsentDecisionData;
use AIArmada\Communications\Data\PlannedDeliveryData;
use AIArmada\Communications\Data\ProviderEventData;
use AIArmada\Communications\Data\ProviderResultData;
use AIArmada\Communications\Data\RecipientSnapshotData;
use AIArmada\Communications\Data\RenderedContentData;
use AIArmada\Communications\Data\ResolvedDestinationData;
use AIArmada\Communications\Data\SuppressionDecisionData;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use Carbon\CarbonImmutable;

test('CommunicationContextData creates with defaults', function (): void {
    $dto = CommunicationContextData::from([]);

    expect($dto->direction)->toBe(CommunicationDirection::Outbound);
    expect($dto->category)->toBe(CommunicationCategory::Transactional);
    expect($dto->priority)->toBe(CommunicationPriority::Normal);
    expect($dto->purpose)->toBeNull();
    expect($dto->metadata)->toBe([]);
});

test('CommunicationContextData creates with overrides', function (): void {
    $dto = CommunicationContextData::from([
        'direction' => 'inbound',
        'category' => 'marketing',
        'priority' => 'high',
        'purpose' => 'campaign',
        'locale' => 'en',
        'timezone' => 'UTC',
        'idempotencyKey' => 'key-123',
        'metadata' => ['channel' => 'mail'],
    ]);

    expect($dto->direction)->toBe(CommunicationDirection::Inbound);
    expect($dto->category)->toBe(CommunicationCategory::Marketing);
    expect($dto->priority)->toBe(CommunicationPriority::High);
    expect($dto->purpose)->toBe('campaign');
    expect($dto->locale)->toBe('en');
    expect($dto->timezone)->toBe('UTC');
    expect($dto->idempotencyKey)->toBe('key-123');
    expect($dto->metadata['channel'])->toBe('mail');
});

test('CommunicationDefinitionData creates', function (): void {
    $dto = new CommunicationDefinitionData(
        notifiableClass: 'App\Models\User',
        notificationClass: 'App\Notifications\Welcome',
        channels: ['mail'],
    );

    expect($dto->notifiableClass)->toBe('App\Models\User');
    expect($dto->notificationClass)->toBe('App\Notifications\Welcome');
    expect($dto->channels)->toBe(['mail']);
});

test('RecipientSnapshotData creates', function (): void {
    $dto = new RecipientSnapshotData(
        identifier: 'user-123',
        displayName: 'John Doe',
        locale: 'en',
        timezone: 'America/New_York',
    );

    expect($dto->identifier)->toBe('user-123');
    expect($dto->displayName)->toBe('John Doe');
    expect($dto->locale)->toBe('en');
});

test('ResolvedDestinationData creates', function (): void {
    $dto = new ResolvedDestinationData(
        destination: 'john@example.com',
        channel: 'mail',
        ciphertext: 'encrypted',
        hash: 'abc123',
        hint: 'john@***.com',
    );

    expect($dto->destination)->toBe('john@example.com');
    expect($dto->channel)->toBe('mail');
    expect($dto->ciphertext)->toBe('encrypted');
    expect($dto->hash)->toBe('abc123');
    expect($dto->hint)->toBe('john@***.com');
});

test('RenderedContentData creates', function (): void {
    $dto = new RenderedContentData(
        channel: 'mail',
        subject: 'Welcome!',
        contentHtml: '<html>...</html>',
    );

    expect($dto->channel)->toBe('mail');
    expect($dto->subject)->toBe('Welcome!');
    expect($dto->contentHtml)->toBe('<html>...</html>');
});

test('PlannedDeliveryData creates', function (): void {
    $dto = new PlannedDeliveryData(
        recipientId: 'rec-123',
        channel: 'mail',
        destinationHash: 'hash123',
        destinationHint: 'user@***.com',
        destinationCiphertext: 'encrypted',
    );

    expect($dto->recipientId)->toBe('rec-123');
    expect($dto->channel)->toBe('mail');
    expect($dto->destinationHash)->toBe('hash123');
    expect($dto->destinationHint)->toBe('user@***.com');
    expect($dto->destinationCiphertext)->toBe('encrypted');
});

test('ProviderResultData creates', function (): void {
    $dto = new ProviderResultData(
        success: true,
        providerMessageId: 'sg_msg_123',
    );

    expect($dto->success)->toBeTrue();
    expect($dto->providerMessageId)->toBe('sg_msg_123');
});

test('ProviderEventData creates', function (): void {
    $dto = new ProviderEventData(
        provider: 'sendgrid',
        providerEventId: 'evt_123',
        providerMessageId: null,
        eventType: 'delivery.delivered',
        occurredAt: CarbonImmutable::parse('2026-06-20T12:00:00Z'),
    );

    expect($dto->eventType)->toBe('delivery.delivered');
    expect($dto->provider)->toBe('sendgrid');
    expect($dto->providerEventId)->toBe('evt_123');
    expect($dto->occurredAt->toIso8601String())->toBe('2026-06-20T12:00:00+00:00');
});

test('SuppressionDecisionData creates', function (): void {
    $dto = new SuppressionDecisionData(
        suppressed: true,
        reason: 'bounced',
    );

    expect($dto->suppressed)->toBeTrue();
    expect($dto->reason)->toBe('bounced');
});

test('ConsentDecisionData creates', function (): void {
    $dto = new ConsentDecisionData(
        consented: true,
        reason: 'registration-form',
    );

    expect($dto->consented)->toBeTrue();
    expect($dto->reason)->toBe('registration-form');
});
