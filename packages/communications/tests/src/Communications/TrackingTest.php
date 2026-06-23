<?php

declare(strict_types=1);

use AIArmada\Communications\Actions\RecordTrackingInteractionAction;
use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationEvent;
use AIArmada\Communications\Models\CommunicationTrackingToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

test('RecordTrackingInteractionAction exists and can be instantiated', function (): void {
    $action = app(RecordTrackingInteractionAction::class);

    expect($action)->toBeInstanceOf(RecordTrackingInteractionAction::class);
});

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

    $event = $action->handle(
        tokenId: $token->id,
        interactionType: 'click',
        metadata: ['url' => 'https://example.com'],
    );

    expect($event)->toBeInstanceOf(CommunicationEvent::class);
    expect($event->event)->toBe('click');
    expect($event->delivery_id)->toBe($delivery->id);
    expect($event->metadata)->toBe(['url' => 'https://example.com']);
    expect($event->source)->toBe(CommunicationEventSource::Tracking);

    $token->refresh();
    expect($token->first_used_at)->not->toBeNull();
    expect($token->last_used_at)->not->toBeNull();
});
