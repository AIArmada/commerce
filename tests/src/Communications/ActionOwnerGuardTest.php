<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Actions\AttachCommunicationReferenceAction;
use AIArmada\Communications\Actions\CancelCommunicationAction;
use AIArmada\Communications\Actions\CancelCommunicationDeliveryAction;
use AIArmada\Communications\Actions\CompleteDeliveryAttemptAction;
use AIArmada\Communications\Actions\RecalculateCommunicationStatusAction;
use AIArmada\Communications\Actions\RecordNotificationSendingAction;
use AIArmada\Communications\Actions\RecordProviderEventAction;
use AIArmada\Communications\Actions\RedactCommunicationPayloadAction;
use AIArmada\Communications\Actions\RetryCommunicationDeliveryAction;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationEventSource;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Enums\RecipientRole;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationAttempt;
use AIArmada\Communications\Models\CommunicationContent;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationEvent;
use AIArmada\Communications\Models\CommunicationRecipient;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

function actionGuardOwner(): User
{
    return User::create([
        'name' => 'Guard Owner',
        'email' => 'guard-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
}

function actionGuardCommunication(array $attributes = []): Communication
{
    $communication = new Communication;
    $communication->forceFill(array_merge([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'owner-guard',
        'status' => CommunicationStatus::Draft,
    ], $attributes));
    $communication->save();

    return $communication;
}

function actionGuardRecipient(Communication $communication): CommunicationRecipient
{
    $recipient = new CommunicationRecipient;
    $recipient->forceFill([
        'communication_id' => $communication->id,
        'role' => RecipientRole::To,
    ]);
    $recipient->save();

    return $recipient;
}

function actionGuardDelivery(
    Communication $communication,
    CommunicationRecipient $recipient,
    array $attributes = [],
): CommunicationDelivery {
    $delivery = new CommunicationDelivery;
    $delivery->forceFill(array_merge([
        'communication_id' => $communication->id,
        'recipient_id' => $recipient->id,
        'channel' => 'mail',
        'provider' => 'array',
        'status' => DeliveryStatus::Pending,
        'attempt_count' => 0,
        'max_attempts' => 3,
        'metadata' => ['api_key' => 'secret-value'],
    ], $attributes));
    $delivery->save();

    return $delivery;
}

function actionGuardAttempt(CommunicationDelivery $delivery): CommunicationAttempt
{
    $attempt = new CommunicationAttempt;
    $attempt->forceFill([
        'delivery_id' => $delivery->id,
        'attempt_number' => 1,
        'provider' => 'array',
        'request_payload' => ['password' => 'secret-value'],
        'response_payload' => ['token' => 'secret-value'],
    ]);
    $attempt->save();

    return $attempt;
}

function actionGuardContent(Communication $communication): CommunicationContent
{
    $content = new CommunicationContent;
    $content->forceFill([
        'communication_id' => $communication->id,
        'channel' => 'mail',
        'payload' => ['api_key' => 'secret-value'],
        'rendered_at' => CarbonImmutable::now(),
    ]);
    $content->save();

    return $content;
}

function actionGuardEvent(
    Communication $communication,
    CommunicationDelivery $delivery,
): CommunicationEvent {
    $event = new CommunicationEvent;
    $event->forceFill([
        'communication_id' => $communication->id,
        'delivery_id' => $delivery->id,
        'source' => CommunicationEventSource::System,
        'event' => 'delivery.sent',
        'occurred_at' => CarbonImmutable::now(),
        'received_at' => CarbonImmutable::now(),
        'payload' => ['api_key' => 'secret-value'],
    ]);
    $event->save();

    return $event;
}

/**
 * @return array{Communication, CommunicationDelivery, CommunicationAttempt, CommunicationContent, CommunicationEvent}
 */
function actionGuardFixtures(array $deliveryAttributes = []): array
{
    $communication = actionGuardCommunication();
    $recipient = actionGuardRecipient($communication);
    $delivery = actionGuardDelivery($communication, $recipient, $deliveryAttributes);
    $attempt = actionGuardAttempt($delivery);
    $content = actionGuardContent($communication);
    $event = actionGuardEvent($communication, $delivery);

    return [$communication, $delivery, $attempt, $content, $event];
}

function actionGuardRun(
    string $case,
    Communication $communication,
    CommunicationDelivery $delivery,
    CommunicationAttempt $attempt,
    CommunicationContent $content,
    CommunicationEvent $event,
): mixed {
    return match ($case) {
        'provider-event-via-delivery' => app(RecordProviderEventAction::class)->handle(
            provider: 'array',
            providerEventId: 'guard-evt-' . uniqid(),
            event: 'delivery',
            deliveryId: $delivery->id,
        ),
        'provider-event-via-attempt' => app(RecordProviderEventAction::class)->handle(
            provider: 'array',
            providerEventId: 'guard-evt-' . uniqid(),
            event: 'delivery',
            attemptId: $attempt->id,
        ),
        'provider-event-via-communication' => app(RecordProviderEventAction::class)->handle(
            provider: 'array',
            providerEventId: 'guard-evt-' . uniqid(),
            event: 'delivery',
            communicationId: $communication->id,
        ),
        'notification-sending' => app(RecordNotificationSendingAction::class)->handle(
            $communication->id,
            $delivery->id,
        ),
        'retry-delivery' => app(RetryCommunicationDeliveryAction::class)->handle($delivery->id),
        'cancel-delivery' => app(CancelCommunicationDeliveryAction::class)->handle($delivery->id),
        'complete-attempt' => app(CompleteDeliveryAttemptAction::class)->handle($attempt->id),
        'recalculate-status' => app(RecalculateCommunicationStatusAction::class)->handle($communication->id),
        'redact-content' => app(RedactCommunicationPayloadAction::class)->handleContent($content->id),
        'redact-delivery' => app(RedactCommunicationPayloadAction::class)->handleDelivery($delivery->id),
        'redact-attempt' => app(RedactCommunicationPayloadAction::class)->handleAttempt($attempt->id),
        'redact-event' => app(RedactCommunicationPayloadAction::class)->handleEvent($event->id),
        'attach-reference' => app(AttachCommunicationReferenceAction::class)->handle(
            $communication->id,
            'events',
            'event-123',
        ),
        'cancel-communication' => app(CancelCommunicationAction::class)->handle($communication->id),
    };
}

function actionGuardAssertPassed(
    string $case,
    mixed $result,
    Communication $communication,
    CommunicationDelivery $delivery,
    CommunicationAttempt $attempt,
): void {
    match ($case) {
        'provider-event-via-delivery',
        'provider-event-via-attempt',
        'provider-event-via-communication' => expect($result)->toBeInstanceOf(CommunicationEvent::class)
            ->and($result->communication_id)->toBe($communication->id),
        'notification-sending' => expect($delivery->fresh()->status)->toBe(DeliveryStatus::Sending),
        'retry-delivery' => expect($result)->toBeInstanceOf(CommunicationAttempt::class)
            ->and($result->attempt_number)->toBe(2),
        'cancel-delivery' => expect($delivery->fresh()->status)->toBe(DeliveryStatus::Cancelled),
        'complete-attempt' => expect($attempt->fresh()->responded_at)->not->toBeNull(),
        'recalculate-status' => expect($result->id)->toBe($communication->id),
        'redact-content', 'redact-delivery', 'redact-attempt', 'redact-event' => expect($result->id)->not->toBeNull(),
        'attach-reference' => expect($result->communication_id)->toBe($communication->id),
        'cancel-communication' => expect($communication->fresh()->status)->toBe(CommunicationStatus::Cancelled),
        default => throw new InvalidArgumentException("Unknown guard case [{$case}]."),
    };
}

function actionGuardCases(): array
{
    return [
        'provider-event-via-delivery' => ['provider-event-via-delivery'],
        'provider-event-via-attempt' => ['provider-event-via-attempt'],
        'provider-event-via-communication' => ['provider-event-via-communication'],
        'notification-sending' => ['notification-sending'],
        'retry-delivery' => ['retry-delivery'],
        'cancel-delivery' => ['cancel-delivery'],
        'complete-attempt' => ['complete-attempt'],
        'recalculate-status' => ['recalculate-status'],
        'redact-content' => ['redact-content'],
        'redact-delivery' => ['redact-delivery'],
        'redact-attempt' => ['redact-attempt'],
        'redact-event' => ['redact-event'],
        'attach-reference' => ['attach-reference'],
        'cancel-communication' => ['cancel-communication'],
    ];
}

beforeEach(function (): void {
    config()->set('communications.features.owner.enabled', true);
    config()->set('communications.features.owner.include_global', false);
    config()->set('communications.features.owner.auto_assign_on_create', true);

    // The owner scope snapshots its config at model boot; force a re-boot so
    // each test queries with its own config instead of a stale snapshot.
    Model::clearBootedModels();
});

afterEach(function (): void {
    Model::clearBootedModels();
});

test('actions hide foreign owner records without revealing existence', function (string $case): void {
    $otherOwner = actionGuardOwner();

    [$communication, $delivery, $attempt, $content, $event] = OwnerContext::withOwner(
        $otherOwner,
        fn (): array => actionGuardFixtures(
            $case === 'retry-delivery'
                ? ['status' => DeliveryStatus::Failed, 'attempt_count' => 1]
                : [],
        ),
    );

    expect(fn () => actionGuardRun($case, $communication, $delivery, $attempt, $content, $event))
        ->toThrow(ModelNotFoundException::class);
})->with(actionGuardCases());

test('actions reject out-of-scope records when the guard applies', function (string $case): void {
    config()->set('communications.features.owner.include_global', true);
    Model::clearBootedModels();

    [$communication, $delivery, $attempt, $content, $event] = OwnerContext::withOwner(
        null,
        fn (): array => actionGuardFixtures(),
    );

    // The guard message pins the action-layer lookup guard, not the model-layer
    // saving hook, which rejects the same records with a different message.
    expect(fn () => actionGuardRun($case, $communication, $delivery, $attempt, $content, $event))
        ->toThrow(AuthorizationException::class, 'not accessible in the current owner scope');
})->with(actionGuardCases());

test('actions accept records owned by the current owner', function (string $case): void {
    [$communication, $delivery, $attempt, $content, $event] = actionGuardFixtures(
        $case === 'retry-delivery'
            ? ['status' => DeliveryStatus::Failed, 'attempt_count' => 1]
            : [],
    );

    $result = actionGuardRun($case, $communication, $delivery, $attempt, $content, $event);

    actionGuardAssertPassed($case, $result, $communication, $delivery, $attempt);
})->with(actionGuardCases());

test('actions keep working when owner scoping is disabled', function (string $case): void {
    [$communication, $delivery, $attempt, $content, $event] = actionGuardFixtures(
        $case === 'retry-delivery'
            ? ['status' => DeliveryStatus::Failed, 'attempt_count' => 1]
            : [],
    );

    config()->set('communications.features.owner.enabled', false);

    $result = actionGuardRun($case, $communication, $delivery, $attempt, $content, $event);

    actionGuardAssertPassed($case, $result, $communication, $delivery, $attempt);
})->with(actionGuardCases());
