<?php

declare(strict_types=1);

use AIArmada\Communications\Actions\AddCommunicationRecipientAction;
use AIArmada\Communications\Actions\ApplyProviderEventAction;
use AIArmada\Communications\Actions\CreateCommunicationAction;
use AIArmada\Communications\Actions\CreateSuppressionAction;
use AIArmada\Communications\Actions\LiftSuppressionAction;
use AIArmada\Communications\Actions\PlanCommunicationDeliveriesAction;
use AIArmada\Communications\Actions\RecordNotificationSendingAction;
use AIArmada\Communications\Actions\RecordProviderEventAction;
use AIArmada\Communications\Actions\RedactCommunicationPayloadAction;
use AIArmada\Communications\Actions\RenderCommunicationContentAction;
use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Data\PlannedDeliveryData;
use AIArmada\Communications\Data\ProviderEventData;
use AIArmada\Communications\Data\RenderedContentData;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Enums\RecipientRole;
use AIArmada\Communications\Enums\SuppressionReason;
use AIArmada\Communications\Enums\TemplateStatus;
use AIArmada\Communications\Events\CommunicationCreated;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationContent;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationRecipient;
use AIArmada\Communications\Models\CommunicationTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;

test('CreateCommunicationAction creates communication and dispatches event', function (): void {
    Event::fake();

    $recorder = app(CommunicationRecorder::class);
    $action = new CreateCommunicationAction($recorder);

    $context = CommunicationContextData::from([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::High,
        'purpose' => 'test-action',
    ]);

    $communication = $action->handle($context);

    expect($communication)->toBeInstanceOf(Communication::class);
    expect($communication->purpose)->toBe('test-action');
    expect($communication->status->value)->toBe('draft');

    Event::assertDispatched(CommunicationCreated::class);
});

test('AddCommunicationRecipientAction adds recipient to communication', function (): void {
    $communication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'recipient-test',
        'status' => CommunicationStatus::Draft,
    ]);

    $action = app(AddCommunicationRecipientAction::class);
    $recipient = $action->handle(
        communicationId: $communication->id,
        recipientType: 'user',
        recipientId: 'user-123',
        role: RecipientRole::To,
        snapshot: ['display_name' => 'John Doe', 'external_key' => 'user-123'],
    );

    expect($recipient->communication_id)->toBe($communication->id);
    expect($recipient->role->value)->toBe('to');
    expect($recipient->recipient_type)->toBe('user');
    expect($recipient->recipient_id)->toBe('user-123');
});

test('CreateSuppressionAction creates suppression', function (): void {
    $action = app(CreateSuppressionAction::class);
    $suppression = $action->handle(
        destinationHash: hash('sha256', 'spam@example.com'),
        channel: 'mail',
        reason: SuppressionReason::Complained,
        metadata: ['source' => 'feedback-loop'],
    );

    expect($suppression->channel)->toBe('mail');
    expect($suppression->reason->value)->toBe('complained');
    expect($suppression->starts_at)->not->toBeNull();
});

test('LiftSuppressionAction lifts a suppression', function (): void {
    $action = app(CreateSuppressionAction::class);
    $suppression = $action->handle(
        destinationHash: hash('sha256', 'bounced@example.com'),
        channel: 'mail',
        reason: SuppressionReason::Bounced,
    );

    $liftAction = app(LiftSuppressionAction::class);
    $result = $liftAction->handle($suppression->id);

    expect($result->id)->toBe($suppression->id);
    expect($result->lifted_at)->not->toBeNull();
});

test('RedactCommunicationPayloadAction redacts content payload', function (): void {
    $communication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'redact-test',
        'status' => CommunicationStatus::Draft,
    ]);

    $content = CommunicationContent::create([
        'communication_id' => $communication->id,
        'channel' => 'mail',
        'subject' => 'Test',
        'content_text' => 'Body',
        'checksum' => md5('Body'),
        'rendered_at' => CarbonImmutable::now(),
        'payload' => ['password' => 'secret123', 'email' => 'test@example.com'],
    ]);

    $action = app(RedactCommunicationPayloadAction::class);
    $action->handleContent($content->id);

    $fresh = CommunicationContent::find($content->id);
    expect($fresh->payload['password'])->toBe('**[REDACTED]**');
    expect($fresh->payload['email'])->toBe('test@example.com');
});

test('CommunicationRecorderService marks sending and sent', function (): void {
    $recorder = app(CommunicationRecorder::class);
    $communication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'recorder-test',
        'status' => CommunicationStatus::Draft,
    ]);

    $recipient = CommunicationRecipient::create([
        'communication_id' => $communication->id,
        'role' => 'to',
    ]);

    $delivery = CommunicationDelivery::create([
        'communication_id' => $communication->id,
        'recipient_id' => $recipient->id,
        'channel' => 'mail',
        'provider' => 'array',
        'status' => DeliveryStatus::Pending,
        'attempt_count' => 0,
        'max_attempts' => 3,
    ]);

    $recorder->markSending($communication->id, $delivery->id);
    expect($delivery->fresh()->status->value)->toBe('sending');

    $recorder->markSent($communication->id, $delivery->id, ['provider_message_id' => 'msg_123']);
    $freshDelivery = $delivery->fresh();
    expect($freshDelivery->status->value)->toBe('sent');
    expect($freshDelivery->provider_message_id)->toBe('msg_123');
});

test('CommunicationRecorderService records failure', function (): void {
    $recorder = app(CommunicationRecorder::class);
    $communication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'failure-test',
        'status' => CommunicationStatus::Draft,
    ]);

    $recipient = CommunicationRecipient::create([
        'communication_id' => $communication->id,
        'role' => 'to',
    ]);

    $delivery = CommunicationDelivery::create([
        'communication_id' => $communication->id,
        'recipient_id' => $recipient->id,
        'channel' => 'mail',
        'provider' => 'array',
        'status' => DeliveryStatus::Pending,
        'attempt_count' => 0,
        'max_attempts' => 3,
    ]);

    $recorder->markFailed($communication->id, $delivery->id, 'Connection timeout');
    $freshDelivery = $delivery->fresh();
    expect($freshDelivery->status->value)->toBe('failed');
    expect($freshDelivery->failure_message)->toBe('Connection timeout');
});

test('notification delivery transitions reject mismatched communication ids', function (): void {
    $communication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'delivery-owner',
        'status' => CommunicationStatus::Draft,
    ]);
    $otherCommunication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'other-delivery-owner',
        'status' => CommunicationStatus::Draft,
    ]);
    $recipient = CommunicationRecipient::create([
        'communication_id' => $communication->id,
        'role' => 'to',
    ]);
    $delivery = CommunicationDelivery::create([
        'communication_id' => $communication->id,
        'recipient_id' => $recipient->id,
        'channel' => 'mail',
        'provider' => 'array',
        'status' => DeliveryStatus::Pending,
        'attempt_count' => 0,
        'max_attempts' => 3,
    ]);

    expect(fn () => app(RecordNotificationSendingAction::class)->handle(
        $otherCommunication->id,
        $delivery->id,
    ))->toThrow(RuntimeException::class, 'Delivery does not belong to the supplied communication.');

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Pending);
});

test('planned deliveries require recipients and content from the same communication', function (): void {
    $communication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'planned-delivery-owner',
        'status' => CommunicationStatus::Draft,
    ]);
    $otherCommunication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'planned-delivery-other',
        'status' => CommunicationStatus::Draft,
    ]);
    $otherRecipient = CommunicationRecipient::create([
        'communication_id' => $otherCommunication->id,
        'role' => 'to',
    ]);

    $plan = new PlannedDeliveryData(
        recipientId: $otherRecipient->id,
        channel: 'mail',
        destinationHash: hash('sha256', 'recipient@example.com'),
        destinationHint: 'r***@example.com',
        destinationCiphertext: 'encrypted',
    );

    expect(fn () => app(PlanCommunicationDeliveriesAction::class)->handle(
        $communication->id,
        [$plan],
    ))->toThrow(ModelNotFoundException::class);

    expect(CommunicationDelivery::query()
        ->where('communication_id', $communication->id)
        ->count())->toBe(0);
});

test('provider events derive and validate their communication links', function (): void {
    $communication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'provider-link-owner',
        'status' => CommunicationStatus::Draft,
    ]);
    $recipient = CommunicationRecipient::create([
        'communication_id' => $communication->id,
        'role' => 'to',
    ]);
    $delivery = CommunicationDelivery::create([
        'communication_id' => $communication->id,
        'recipient_id' => $recipient->id,
        'channel' => 'mail',
        'provider' => 'array',
        'status' => DeliveryStatus::Pending,
        'attempt_count' => 0,
        'max_attempts' => 3,
    ]);

    $event = app(RecordProviderEventAction::class)->handle(
        provider: 'test',
        providerEventId: 'provider-derived-communication',
        event: 'delivery',
        deliveryId: $delivery->id,
    );

    expect($event->communication_id)->toBe($communication->id)
        ->and($event->delivery_id)->toBe($delivery->id);
});

test('CommunicationRecorderService cancels communication', function (): void {
    $recorder = app(CommunicationRecorder::class);
    $communication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'cancel-test',
        'status' => CommunicationStatus::Draft,
    ]);

    $recorder->cancelCommunication($communication->id);
    $fresh = $communication->fresh();

    expect($fresh->status->value)->toBe('cancelled');
    expect($fresh->cancelled_at)->not->toBeNull();
});

test('RenderCommunicationContentAction works with null renderer', function (): void {
    $action = app(RenderCommunicationContentAction::class);
    $template = CommunicationTemplate::create([
        'key' => 'test-template',
        'name' => 'Test Template',
        'category' => 'mail',
        'status' => TemplateStatus::Draft,
    ]);

    $content = $action->handle($template, 'mail', 'en', []);

    expect($content)->toBeInstanceOf(RenderedContentData::class);
    expect($content->channel)->toBe('mail');
});

test('ApplyProviderEventAction allows events without provider ids', function (): void {
    $action = app(ApplyProviderEventAction::class);

    $communicationOne = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'provider-event-one',
        'status' => CommunicationStatus::Draft,
    ]);

    $recipientOne = CommunicationRecipient::create([
        'communication_id' => $communicationOne->id,
        'role' => 'to',
    ]);

    $deliveryOne = CommunicationDelivery::create([
        'communication_id' => $communicationOne->id,
        'recipient_id' => $recipientOne->id,
        'channel' => 'mail',
        'provider' => 'array',
        'status' => DeliveryStatus::Pending,
        'attempt_count' => 0,
        'max_attempts' => 3,
    ]);

    $communicationTwo = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'provider-event-two',
        'status' => CommunicationStatus::Draft,
    ]);

    $recipientTwo = CommunicationRecipient::create([
        'communication_id' => $communicationTwo->id,
        'role' => 'to',
    ]);

    $deliveryTwo = CommunicationDelivery::create([
        'communication_id' => $communicationTwo->id,
        'recipient_id' => $recipientTwo->id,
        'channel' => 'mail',
        'provider' => 'array',
        'status' => DeliveryStatus::Pending,
        'attempt_count' => 0,
        'max_attempts' => 3,
    ]);

    $action->handle(new ProviderEventData(
        provider: 'sendgrid',
        providerEventId: null,
        providerMessageId: null,
        eventType: 'delivery',
        occurredAt: CarbonImmutable::now(),
        communicationId: $communicationOne->id,
        deliveryId: $deliveryOne->id,
        payload: ['delivery_id' => $deliveryOne->id],
    ));

    $action->handle(new ProviderEventData(
        provider: 'sendgrid',
        providerEventId: null,
        providerMessageId: null,
        eventType: 'delivery',
        occurredAt: CarbonImmutable::now(),
        communicationId: $communicationTwo->id,
        deliveryId: $deliveryTwo->id,
        payload: ['delivery_id' => $deliveryTwo->id],
    ));

    expect($deliveryOne->fresh()->status->value)->toBe('delivered');
    expect($deliveryTwo->fresh()->status->value)->toBe('delivered');
});
