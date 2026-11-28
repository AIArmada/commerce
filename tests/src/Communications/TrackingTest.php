<?php

declare(strict_types=1);

use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Features\Delivery\Models\CommunicationEvent;
use AIArmada\Communications\Features\Tracking\Actions\CreateTrackingTokenAction;
use AIArmada\Communications\Features\Tracking\Actions\RecordTrackingInteractionAction;
use AIArmada\Communications\Features\Tracking\Models\CommunicationTrackingToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

test('RecordTrackingInteractionAction handle throws for non-existent token', function (): void {
    $action = app(RecordTrackingInteractionAction::class);

    $nonExistentId = (string) Str::uuid();

    expect(fn () => $action->handle(
        tokenId: $nonExistentId,
        interactionType: 'click',
    ))->toThrow(ModelNotFoundException::class);
});

test('RecordTrackingInteractionAction handle records interaction with valid token', function (): void {
    $delivery = new CommunicationDelivery;
    $delivery->id = (string) Str::uuid();
    $delivery->communication_id = (string) Str::uuid();
    $delivery->recipient_id = (string) Str::uuid();
    $delivery->channel = 'email';
    $delivery->provider = 'ses';
    $delivery->status = DeliveryStatus::Sent;
    $delivery->save();

    $token = new CommunicationTrackingToken;
    $token->id = (string) Str::uuid();
    $token->delivery_id = $delivery->id;
    $token->kind = 'click';
    $token->token_hash = hash('sha256', (string) Str::random(32));
    $token->save();

    $action = app(RecordTrackingInteractionAction::class);

    $eventId = $action->handle(
        tokenId: $token->id,
        interactionType: 'click',
        metadata: ['url' => 'https://example.com'],
    );

    $event = CommunicationEvent::query()->findOrFail($eventId);

    expect($event)->toBeInstanceOf(CommunicationEvent::class);
    expect($event->event)->toBe('click');
    expect($event->communication_id)->toBe($delivery->communication_id);
    expect($event->delivery_id)->toBe($delivery->id);
    expect($event->metadata)->toBe(['url' => 'https://example.com']);
    expect($event->source)->toBe(CommunicationEventSource::Tracking);

    $token->refresh();
    expect($token->first_used_at)->not->toBeNull();
    expect($token->last_used_at)->not->toBeNull();
});

test('tracking token creation does not return a token when persistence is cancelled', function (): void {
    $delivery = new CommunicationDelivery;
    $delivery->communication_id = (string) Str::uuid();
    $delivery->recipient_id = (string) Str::uuid();
    $delivery->channel = 'email';
    $delivery->provider = 'ses';
    $delivery->status = DeliveryStatus::Sent;
    $delivery->save();

    CommunicationTrackingToken::creating(static fn (CommunicationTrackingToken $token): bool => $token->delivery_id !== $delivery->id);

    expect(fn () => app(CreateTrackingTokenAction::class)->handle($delivery->id, 'click'))
        ->toThrow(RuntimeException::class, 'Tracking token creation was cancelled.');

    expect(CommunicationTrackingToken::query()->where('delivery_id', $delivery->id)->exists())->toBeFalse();
});

test('tracking event cancellation rolls back token usage', function (): void {
    $delivery = new CommunicationDelivery;
    $delivery->communication_id = (string) Str::uuid();
    $delivery->recipient_id = (string) Str::uuid();
    $delivery->channel = 'email';
    $delivery->provider = 'ses';
    $delivery->status = DeliveryStatus::Sent;
    $delivery->save();

    $created = app(CreateTrackingTokenAction::class)->handle($delivery->id, 'click');
    CommunicationEvent::creating(static fn (CommunicationEvent $event): bool => $event->delivery_id !== $delivery->id);

    expect(fn () => app(RecordTrackingInteractionAction::class)->handle($created->trackingTokenId, 'click'))
        ->toThrow(RuntimeException::class, 'Tracking event creation was cancelled.');

    $token = CommunicationTrackingToken::query()->findOrFail($created->trackingTokenId);
    expect($token->first_used_at)->toBeNull()
        ->and($token->last_used_at)->toBeNull();
});
