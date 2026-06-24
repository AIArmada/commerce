<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Communications\Enums\NotificationFamily;
use AIArmada\Communications\Enums\NotificationPriority;
use AIArmada\Communications\Enums\NotificationTrigger;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\NotificationInbox;

beforeEach(function (): void {
    config()->set('communications.features.owner.enabled', false);

    $this->user = User::create([
        'name' => 'Inbox User',
        'email' => 'inbox-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $this->communication = Communication::create([
        'direction' => 'internal',
        'category' => 'internal',
        'priority' => 'normal',
        'purpose' => 'notification-test',
        'status' => 'completed',
    ]);
});

test('creates with minimal attributes', function (): void {
    $inbox = NotificationInbox::create([
        'recipient_type' => $this->user::class,
        'recipient_id' => $this->user->id,
        'family' => NotificationFamily::EventReminder,
        'priority' => NotificationPriority::Normal,
        'trigger' => NotificationTrigger::EventPublished,
        'title' => 'Test Notification',
    ]);

    expect($inbox->id)->toBeUuid();
    expect($inbox->title)->toBe('Test Notification');
    expect($inbox->body)->toBeNull();
});

test('has uuid primary key', function (): void {
    $inbox = NotificationInbox::create([
        'recipient_type' => $this->user::class,
        'recipient_id' => $this->user->id,
        'family' => NotificationFamily::EventReminder,
        'priority' => NotificationPriority::Normal,
        'trigger' => NotificationTrigger::EventPublished,
        'title' => 'UUID Test',
    ]);

    expect($inbox->id)->toBeUuid();
    expect($inbox->getKeyType())->toBe('string');
});

test('casts family enum correctly', function (): void {
    $inbox = NotificationInbox::create([
        'recipient_type' => $this->user::class,
        'recipient_id' => $this->user->id,
        'family' => NotificationFamily::EventUpdate,
        'priority' => NotificationPriority::Normal,
        'trigger' => NotificationTrigger::EventPublished,
        'title' => 'Family Enum Cast',
    ]);

    $fresh = NotificationInbox::find($inbox->id);
    expect($fresh->family)->toBeInstanceOf(NotificationFamily::class);
    expect($fresh->family)->toBe(NotificationFamily::EventUpdate);
});

test('casts priority enum correctly', function (): void {
    $inbox = NotificationInbox::create([
        'recipient_type' => $this->user::class,
        'recipient_id' => $this->user->id,
        'family' => NotificationFamily::EventReminder,
        'priority' => NotificationPriority::High,
        'trigger' => NotificationTrigger::EventPublished,
        'title' => 'Priority Enum Cast',
    ]);

    $fresh = NotificationInbox::find($inbox->id);
    expect($fresh->priority)->toBeInstanceOf(NotificationPriority::class);
    expect($fresh->priority)->toBe(NotificationPriority::High);
});

test('casts trigger enum correctly', function (): void {
    $inbox = NotificationInbox::create([
        'recipient_type' => $this->user::class,
        'recipient_id' => $this->user->id,
        'family' => NotificationFamily::EventReminder,
        'priority' => NotificationPriority::Normal,
        'trigger' => NotificationTrigger::EventCancelled,
        'title' => 'Trigger Enum Cast',
    ]);

    $fresh = NotificationInbox::find($inbox->id);
    expect($fresh->trigger)->toBeInstanceOf(NotificationTrigger::class);
    expect($fresh->trigger)->toBe(NotificationTrigger::EventCancelled);
});

test('has recipient morphTo relationship', function (): void {
    $inbox = NotificationInbox::create([
        'recipient_type' => $this->user::class,
        'recipient_id' => $this->user->id,
        'family' => NotificationFamily::EventReminder,
        'priority' => NotificationPriority::Normal,
        'trigger' => NotificationTrigger::EventPublished,
        'title' => 'Recipient Test',
    ]);

    expect($inbox->recipient)->toBeInstanceOf(User::class);
    expect($inbox->recipient->id)->toBe($this->user->id);
});

test('has communication belongsTo relationship', function (): void {
    $inbox = NotificationInbox::create([
        'recipient_type' => $this->user::class,
        'recipient_id' => $this->user->id,
        'communication_id' => $this->communication->id,
        'family' => NotificationFamily::EventReminder,
        'priority' => NotificationPriority::Normal,
        'trigger' => NotificationTrigger::EventPublished,
        'title' => 'Communication Test',
    ]);

    expect($inbox->communication)->toBeInstanceOf(Communication::class);
    expect($inbox->communication->id)->toBe($this->communication->id);
});

test('scopeUnread returns only unread notifications', function (): void {
    NotificationInbox::create([
        'recipient_type' => $this->user::class,
        'recipient_id' => $this->user->id,
        'family' => NotificationFamily::EventReminder,
        'priority' => NotificationPriority::Normal,
        'trigger' => NotificationTrigger::EventPublished,
        'title' => 'Unread',
    ]);

    NotificationInbox::create([
        'recipient_type' => $this->user::class,
        'recipient_id' => $this->user->id,
        'family' => NotificationFamily::SystemAnnouncement,
        'priority' => NotificationPriority::Normal,
        'trigger' => NotificationTrigger::SystemAlert,
        'title' => 'Read',
        'read_at' => now(),
    ]);

    expect(NotificationInbox::unread()->count())->toBe(1);
    expect(NotificationInbox::unread()->first()->title)->toBe('Unread');
});

test('scopeArchived returns only archived notifications', function (): void {
    NotificationInbox::create([
        'recipient_type' => $this->user::class,
        'recipient_id' => $this->user->id,
        'family' => NotificationFamily::EventReminder,
        'priority' => NotificationPriority::Normal,
        'trigger' => NotificationTrigger::EventPublished,
        'title' => 'Active',
    ]);

    NotificationInbox::create([
        'recipient_type' => $this->user::class,
        'recipient_id' => $this->user->id,
        'family' => NotificationFamily::SystemAnnouncement,
        'priority' => NotificationPriority::Normal,
        'trigger' => NotificationTrigger::SystemAlert,
        'title' => 'Archived',
        'archived_at' => now(),
    ]);

    expect(NotificationInbox::archived()->count())->toBe(1);
    expect(NotificationInbox::archived()->first()->title)->toBe('Archived');
});

test('config-driven table name', function (): void {
    config()->set('communications.database.tables.notification_inboxes', 'custom_notification_inboxes');

    $inbox = new NotificationInbox;
    expect($inbox->getTable())->toBe('custom_notification_inboxes');
});

test('default table name from config', function (): void {
    $inbox = new NotificationInbox;
    expect($inbox->getTable())->toBe('notification_inboxes');
});

test('can be created with read_at and archived_at timestamps', function (): void {
    $now = now();

    $inbox = NotificationInbox::create([
        'recipient_type' => $this->user::class,
        'recipient_id' => $this->user->id,
        'family' => NotificationFamily::EventReminder,
        'priority' => NotificationPriority::Normal,
        'trigger' => NotificationTrigger::EventPublished,
        'title' => 'Timestamped',
        'read_at' => $now,
        'archived_at' => $now,
    ]);

    $fresh = NotificationInbox::find($inbox->id);
    expect($fresh->read_at)->not->toBeNull();
    expect($fresh->archived_at)->not->toBeNull();
});

test('stores data JSON correctly', function (): void {
    $data = ['key' => 'value', 'nested' => ['foo' => 'bar'], 'count' => 42];

    $inbox = NotificationInbox::create([
        'recipient_type' => $this->user::class,
        'recipient_id' => $this->user->id,
        'family' => NotificationFamily::EventReminder,
        'priority' => NotificationPriority::Normal,
        'trigger' => NotificationTrigger::EventPublished,
        'title' => 'Data JSON',
        'data' => $data,
    ]);

    $fresh = NotificationInbox::find($inbox->id);
    expect($fresh->data)->toBe($data);
    expect($fresh->data['key'])->toBe('value');
    expect($fresh->data['nested']['foo'])->toBe('bar');
    expect($fresh->data['count'])->toBe(42);
});

test('can be created without communication_id', function (): void {
    $inbox = NotificationInbox::create([
        'recipient_type' => $this->user::class,
        'recipient_id' => $this->user->id,
        'family' => NotificationFamily::WelcomeMessage,
        'priority' => NotificationPriority::Low,
        'trigger' => NotificationTrigger::AccountCreated,
        'title' => 'No Communication',
    ]);

    expect($inbox->communication_id)->toBeNull();
    expect($inbox->communication)->toBeNull();
});
