<?php

declare(strict_types=1);

use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationRecipient;
use AIArmada\Communications\Notifications\BaseCommunicationNotification;
use AIArmada\Communications\Traits\HasCommunicationContext;
use Illuminate\Notifications\Notification;

beforeEach(function (): void {
    $this->communication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'notification-test',
        'status' => CommunicationStatus::Draft,
    ]);
});

test('HasCommunicationContext trait attaches IDs', function (): void {
    $notification = new class extends Notification
    {
        use HasCommunicationContext;

        public function via(object $notifiable): array
        {
            return ['mail'];
        }
    };

    $notification->withCommunicationContext(
        communicationId: 'comm-123',
        deliveryIdsByChannel: ['mail' => 'del-123'],
        ownerType: 'user',
        ownerId: 'usr-1',
    );

    expect($notification->communicationId())->toBe('comm-123');
    expect($notification->deliveryIdForChannel('mail'))->toBe('del-123');
    expect($notification->deliveryIdForChannel('sms'))->toBeNull();
});

test('BaseCommunicationNotification inherits context', function (): void {
    $notification = new class extends BaseCommunicationNotification
    {
        public function via(object $notifiable): array
        {
            return ['mail'];
        }
    };

    $notification->withCommunicationContext('comm-456', ['mail' => 'del-456']);

    expect($notification->communicationId())->toBe('comm-456');
});

test('recordCommunicationFailure marks delivery as failed', function (): void {
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

    $notification = new class extends Notification
    {
        use HasCommunicationContext;

        public function via(object $notifiable): array
        {
            return ['mail'];
        }
    };

    $notification->withCommunicationContext(
        communicationId: $this->communication->id,
        deliveryIdsByChannel: ['mail' => $delivery->id],
    );

    $notification->recordCommunicationFailure(new RuntimeException('Delivery failed'));

    $fresh = $delivery->fresh();
    expect($fresh->status->value)->toBe('failed');
    expect($fresh->failure_message)->toBe('Delivery failed');
});

test('recordCommunicationFailure handles null communicationId gracefully', function (): void {
    $notification = new class extends Notification
    {
        use HasCommunicationContext;

        public function via(object $notifiable): array
        {
            return ['mail'];
        }
    };

    $notification->recordCommunicationFailure(new RuntimeException('Should be silent'));

    expect(true)->toBeTrue();
});
