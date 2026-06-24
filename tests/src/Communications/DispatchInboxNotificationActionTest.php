<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Communications\Actions\DispatchInboxNotificationAction;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\NotificationFamily;
use AIArmada\Communications\Enums\NotificationPriority;
use AIArmada\Communications\Enums\NotificationTrigger;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\NotificationInbox;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config()->set('communications.features.owner.enabled', false);

    $this->action = app(DispatchInboxNotificationAction::class);

    $this->user = User::create([
        'name' => 'Action User',
        'email' => 'action-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
});

test('dispatches an inbox notification with a created Communication record', function (): void {
    $inbox = $this->action->handle(
        recipient: $this->user,
        title: 'Action Notification',
        body: 'Notification body',
        family: NotificationFamily::EventReminder,
        priority: NotificationPriority::Normal,
        trigger: NotificationTrigger::EventPublished,
    );

    expect($inbox)->toBeInstanceOf(NotificationInbox::class);
    expect($inbox->title)->toBe('Action Notification');
    expect($inbox->body)->toBe('Notification body');
});

test('creates the NotificationInbox record linked to the Communication', function (): void {
    $inbox = $this->action->handle(
        recipient: $this->user,
        title: 'Linked Notification',
        body: 'Linked body',
        family: NotificationFamily::EventReminder,
        priority: NotificationPriority::Normal,
        trigger: NotificationTrigger::EventPublished,
    );

    expect($inbox->communication_id)->not->toBeNull();

    $communication = Communication::find($inbox->communication_id);
    expect($communication)->toBeInstanceOf(Communication::class);
    expect($communication->id)->toBe($inbox->communication_id);
});

test('works within a transaction', function (): void {
    $inbox = DB::transaction(function (): NotificationInbox {
        return $this->action->handle(
            recipient: $this->user,
            title: 'Transactional Dispatch',
            body: 'Created inside a transaction',
            family: NotificationFamily::WelcomeMessage,
            priority: NotificationPriority::Low,
            trigger: NotificationTrigger::AccountCreated,
        );
    });

    expect($inbox)->toBeInstanceOf(NotificationInbox::class);
    expect($inbox->title)->toBe('Transactional Dispatch');
});

test('verifies Communication was created with expected attributes', function (): void {
    $inbox = $this->action->handle(
        recipient: $this->user,
        title: 'Verify Communication',
        body: 'Check communication attributes',
        family: NotificationFamily::SecurityAlert,
        priority: NotificationPriority::Urgent,
        trigger: NotificationTrigger::SecurityEvent,
    );

    $communication = Communication::find($inbox->communication_id);

    expect($communication->direction->value)->toBe('internal');
    expect($communication->direction)->toBe(CommunicationDirection::Internal);

    expect($communication->category->value)->toBe('internal');
    expect($communication->category)->toBe(CommunicationCategory::Internal);

    expect($communication->purpose)->toBe('inbox_notification');

    expect($communication->status->value)->toBe('completed');
    expect($communication->status)->toBe(CommunicationStatus::Completed);

    expect($communication->priority->value)->toBe('normal');
    expect($communication->priority)->toBe(CommunicationPriority::Normal);

    expect($communication->completed_at)->not->toBeNull();
});

test('passes data and scheduledAt through the action', function (): void {
    $data = ['order_id' => 'ORD-001', 'amount' => 99.99];
    $scheduledAt = CarbonImmutable::now()->addHour();

    $inbox = $this->action->handle(
        recipient: $this->user,
        title: 'With Data',
        body: 'Has optional data',
        family: NotificationFamily::EventReminder,
        priority: NotificationPriority::Normal,
        trigger: NotificationTrigger::EventPublished,
        data: $data,
        scheduledAt: $scheduledAt,
    );

    expect($inbox->data)->toBe($data);
    expect($inbox->scheduled_at)->not->toBeNull();
});
