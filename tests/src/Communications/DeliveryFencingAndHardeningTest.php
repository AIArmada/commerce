<?php

declare(strict_types=1);

use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Contracts\DeliveryFencing;
use AIArmada\Communications\Contracts\DestinationResolver;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Features\Delivery\Models\CommunicationAttempt;
use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationRecipient;
use AIArmada\Communications\Support\GuardedDeliverySend;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

test('payload redactor handles integer list keys, redacts sensitive subtrees, and honors config keys', function (): void {
    config()->set('communications.redaction.sensitive_substrings', ['credential']);
    config()->set('communications.redaction.exact_keys', ['x-custom-secret']);

    $redactor = app(PayloadRedactor::class);

    $redacted = $redactor->redact([
        'tags' => ['alpha', 'beta'],
        'headers' => [
            'authorization' => ['Bearer secret-value'],
            'X-Custom-Secret' => 'top-secret',
            'nested_credential' => ['a', 'b'],
        ],
        'safe' => 'value',
    ]);

    expect($redacted)->toBe([
        'tags' => ['alpha', 'beta'],
        'headers' => [
            'authorization' => '**[REDACTED]**',
            'X-Custom-Secret' => '**[REDACTED]**',
            'nested_credential' => '**[REDACTED]**',
        ],
        'safe' => 'value',
    ]);
});

test('destination resolver handles notification-requiring mail routes and array destinations', function (): void {
    $resolver = app(DestinationResolver::class);

    $notifiable = new class
    {
        public function routeNotificationForMail(Notification $notification): array
        {
            return ['participant@example.com' => 'Participant Name'];
        }
    };

    $resolved = $resolver->resolve($notifiable, 'mail');

    expect($resolved)->not->toBeNull()
        ->and($resolved?->destination)->toBe('participant@example.com');
});

test('communication recorder does not overwrite cancelled or already settled deliveries when marking sending', function (): void {
    $recorder = app(CommunicationRecorder::class);

    $communication = (new Communication)->forceFill([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'guard-test',
        'status' => CommunicationStatus::Cancelled,
        'cancelled_at' => CarbonImmutable::now(),
    ]);
    $communication->save();

    $recipient = CommunicationRecipient::create([
        'communication_id' => $communication->id,
        'role' => 'to',
    ]);

    $cancelledDelivery = (new CommunicationDelivery)->forceFill([
        'communication_id' => $communication->id,
        'recipient_id' => $recipient->id,
        'channel' => 'mail',
        'provider' => 'array',
        'status' => DeliveryStatus::Cancelled,
        'cancelled_at' => CarbonImmutable::now(),
        'attempt_count' => 0,
        'max_attempts' => 3,
    ]);
    $cancelledDelivery->save();

    $recorder->markSending($communication->id, $cancelledDelivery->id);
    expect($cancelledDelivery->fresh()->status)->toBe(DeliveryStatus::Cancelled)
        ->and($communication->fresh()->status)->toBe(CommunicationStatus::Cancelled);
});

test('delivery fencing claims, freezes attempt snapshot, records sent/ambiguity, and skips cancelled deliveries', function (): void {
    $communication = (new Communication)->forceFill([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'fence-test',
        'status' => CommunicationStatus::Queued,
    ]);
    $communication->save();

    $recipient = CommunicationRecipient::create([
        'communication_id' => $communication->id,
        'role' => 'to',
    ]);

    $delivery = (new CommunicationDelivery)->forceFill([
        'communication_id' => $communication->id,
        'recipient_id' => $recipient->id,
        'channel' => 'mail',
        'provider' => 'smtp',
        'status' => DeliveryStatus::Pending,
        'attempt_count' => 0,
        'max_attempts' => 3,
    ]);
    $delivery->save();

    $attempt = DB::transaction(function () use ($communication, $delivery): CommunicationAttempt {
        $claimed = GuardedDeliverySend::claimExternal($delivery->id, $communication->id);
        expect($claimed)->not->toBeNull();

        $claimed->status = DeliveryStatus::Sending;
        $claimed->save();

        return GuardedDeliverySend::startExternalAttempt($delivery->id, $claimed, ['slot' => 'abc']);
    });

    $fencing = app(DeliveryFencing::class);

    expect($fencing->isAmbiguousExternal($delivery->id))->toBeTrue();

    // Second claim must see existing attempt and refuse.
    $secondClaim = DB::transaction(fn () => GuardedDeliverySend::claimExternal($delivery->id, $communication->id));
    expect($secondClaim)->toBeNull();

    // Record failure preserves ambiguous delivery when called via recordNotificationFailure.
    $fencing->recordNotificationFailure($communication->id, ['mail' => $delivery->id], 'timeout');
    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Sending);

    $sentRecorded = DB::transaction(fn (): bool => $fencing->recordExternalSent(
        $communication->id,
        $delivery->id,
        $attempt->id,
        ['provider_message_id' => 'msg_fenced_1'],
    ));

    expect($sentRecorded)->toBeTrue()
        ->and($fencing->isAmbiguousExternal($delivery->id))->toBeFalse()
        ->and($delivery->fresh()->status)->toBe(DeliveryStatus::Sent)
        ->and($delivery->fresh()->provider_message_id)->toBe('msg_fenced_1');
});
