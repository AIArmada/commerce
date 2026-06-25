<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Enums\NotificationFamily;
use AIArmada\Communications\Enums\NotificationPriority;
use AIArmada\Communications\Enums\NotificationTrigger;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\NotificationInbox;
use AIArmada\Communications\Services\NotificationInboxService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    config()->set('communications.features.owner.enabled', false);

    $this->service = app(NotificationInboxService::class);

    $this->user = User::create([
        'name' => 'Service User',
        'email' => 'service-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $this->communication = Communication::create([
        'direction' => 'internal',
        'category' => 'internal',
        'priority' => 'normal',
        'purpose' => 'inbox_service_test',
        'status' => 'completed',
    ]);
});

test('can create inbox notification via the service', function (): void {
    $inbox = $this->service->create(
        recipient: $this->user,
        communication: $this->communication,
        family: NotificationFamily::EventReminder,
        priority: NotificationPriority::Normal,
        trigger: NotificationTrigger::EventPublished,
        title: 'Service Created',
        body: 'Notification body text',
        data: ['source' => 'test'],
    );

    expect($inbox)->toBeInstanceOf(NotificationInbox::class);
    expect($inbox->id)->toBeUuid();
    expect($inbox->title)->toBe('Service Created');
    expect($inbox->body)->toBe('Notification body text');
    expect($inbox->data)->toBe(['source' => 'test']);
    expect($inbox->recipient_type)->toBe($this->user::class);
    expect($inbox->recipient_id)->toBe($this->user->id);
    expect($inbox->communication_id)->toBe($this->communication->id);
    expect($inbox->family)->toBe(NotificationFamily::EventReminder);
    expect($inbox->priority)->toBe(NotificationPriority::Normal);
    expect($inbox->trigger)->toBe(NotificationTrigger::EventPublished);
});

test('can create inbox notifications from a recipient relation', function (): void {
    $inbox = $this->service->create(
        recipient: $this->user->morphMany(NotificationInbox::class, 'recipient'),
        communication: $this->communication,
        family: NotificationFamily::EventReminder,
        priority: NotificationPriority::Normal,
        trigger: NotificationTrigger::EventPublished,
        title: 'Relation Created',
        body: 'Notification body text',
        data: ['source' => 'test'],
    );

    expect($inbox->recipient_type)->toBe($this->user->getMorphClass());
    expect($inbox->recipient_id)->toBe($this->user->id);
});

test('can mark as read', function (): void {
    $inbox = $this->service->create(
        recipient: $this->user,
        communication: $this->communication,
        family: NotificationFamily::EventReminder,
        priority: NotificationPriority::Normal,
        trigger: NotificationTrigger::EventPublished,
        title: 'Mark Read Test',
    );

    expect($inbox->read_at)->toBeNull();

    $this->service->markAsRead($this->user, $inbox->id);

    $fresh = NotificationInbox::find($inbox->id);
    expect($fresh->read_at)->not->toBeNull();
});

test('can mark all as read', function (): void {
    $inboxA = $this->service->create(
        recipient: $this->user,
        communication: $this->communication,
        family: NotificationFamily::EventReminder,
        priority: NotificationPriority::Normal,
        trigger: NotificationTrigger::EventPublished,
        title: 'Unread A',
    );

    $inboxB = $this->service->create(
        recipient: $this->user,
        communication: $this->communication,
        family: NotificationFamily::SystemAnnouncement,
        priority: NotificationPriority::Normal,
        trigger: NotificationTrigger::SystemAlert,
        title: 'Unread B',
    );

    expect($inboxA->read_at)->toBeNull();
    expect($inboxB->read_at)->toBeNull();

    $this->service->markAllAsRead(
        $this->user->morphMany(NotificationInbox::class, 'recipient'),
    );

    expect(NotificationInbox::unread()->count())->toBe(0);
    expect(NotificationInbox::query()->whereNotNull('read_at')->count())->toBe(2);
});

test('can archive', function (): void {
    $inbox = $this->service->create(
        recipient: $this->user,
        communication: $this->communication,
        family: NotificationFamily::EventReminder,
        priority: NotificationPriority::Normal,
        trigger: NotificationTrigger::EventPublished,
        title: 'Archive Test',
    );

    expect($inbox->archived_at)->toBeNull();

    $this->service->archive($this->user, $inbox->id);

    $fresh = NotificationInbox::find($inbox->id);
    expect($fresh->archived_at)->not->toBeNull();
});

test('cannot mutate another recipients inbox entry', function (): void {
    $otherUser = User::create([
        'name' => 'Other Recipient',
        'email' => 'other-recipient-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $inbox = $this->service->create(
        recipient: $otherUser,
        communication: $this->communication,
        family: NotificationFamily::EventReminder,
        priority: NotificationPriority::Normal,
        trigger: NotificationTrigger::EventPublished,
        title: 'Other Recipient Inbox',
    );

    $this->service->markAsRead($this->user, $inbox->id);
    $this->service->archive($this->user, $inbox->id);

    expect($inbox->fresh()->read_at)->toBeNull()
        ->and($inbox->fresh()->archived_at)->toBeNull();
});

test('prune removes archived entries older than the threshold', function (): void {
    $oldInbox = $this->service->create(
        recipient: $this->user,
        communication: $this->communication,
        family: NotificationFamily::EventReminder,
        priority: NotificationPriority::Normal,
        trigger: NotificationTrigger::EventPublished,
        title: 'Old Archived',
    );

    $newInbox = $this->service->create(
        recipient: $this->user,
        communication: $this->communication,
        family: NotificationFamily::SystemAnnouncement,
        priority: NotificationPriority::Normal,
        trigger: NotificationTrigger::SystemAlert,
        title: 'New Archived',
    );

    // Manually set archived_at timestamps
    NotificationInbox::where('id', $oldInbox->id)->update([
        'archived_at' => CarbonImmutable::now()->subDays(100),
    ]);

    NotificationInbox::where('id', $newInbox->id)->update([
        'archived_at' => CarbonImmutable::now()->subDays(10),
    ]);

    $pruned = $this->service->prune(before: CarbonImmutable::now()->subDays(30));

    expect($pruned)->toBe(1);
    expect(NotificationInbox::find($oldInbox->id))->toBeNull();
    expect(NotificationInbox::find($newInbox->id))->not->toBeNull();
});

test('prune removes archived entries across owners', function (): void {
    config()->set('communications.features.owner.enabled', true);
    Model::clearBootedModels();

    $currentOwner = OwnerContext::resolve();
    $otherOwner = User::create([
        'name' => 'Other Owner',
        'email' => 'owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $currentOwnerInbox = OwnerContext::withOwner($currentOwner, function (): NotificationInbox {
        $communication = Communication::create([
            'direction' => 'internal',
            'category' => 'internal',
            'priority' => 'normal',
            'purpose' => 'current-owner-prune-test',
            'status' => 'completed',
        ]);

        return $this->service->create(
            recipient: $this->user,
            communication: $communication,
            family: NotificationFamily::EventReminder,
            priority: NotificationPriority::Normal,
            trigger: NotificationTrigger::EventPublished,
            title: 'Current Owner Archived',
        );
    });

    $otherOwnerInbox = OwnerContext::withOwner($otherOwner, function () use ($otherOwner): NotificationInbox {
        $communication = Communication::create([
            'direction' => 'internal',
            'category' => 'internal',
            'priority' => 'normal',
            'purpose' => 'other-owner-prune-test',
            'status' => 'completed',
        ]);

        return $this->service->create(
            recipient: $otherOwner,
            communication: $communication,
            family: NotificationFamily::SystemAnnouncement,
            priority: NotificationPriority::Normal,
            trigger: NotificationTrigger::SystemAlert,
            title: 'Other Owner Archived',
        );
    });

    NotificationInbox::query()
        ->where('id', $currentOwnerInbox->id)
        ->update(['archived_at' => CarbonImmutable::now()->subDays(100)]);

    OwnerContext::withOwner($otherOwner, function () use ($otherOwnerInbox): void {
        NotificationInbox::query()
            ->where('id', $otherOwnerInbox->id)
            ->update(['archived_at' => CarbonImmutable::now()->subDays(100)]);
    });

    $pruned = $this->service->prune(before: CarbonImmutable::now()->subDays(30));

    expect($pruned)->toBe(2);
    expect(NotificationInbox::query()->withoutOwnerScope()->find($currentOwnerInbox->id))->toBeNull();
    expect(NotificationInbox::query()->withoutOwnerScope()->find($otherOwnerInbox->id))->toBeNull();
});

test('prune returns 0 when no archived entries exceed threshold', function (): void {
    $this->service->create(
        recipient: $this->user,
        communication: $this->communication,
        family: NotificationFamily::EventReminder,
        priority: NotificationPriority::Normal,
        trigger: NotificationTrigger::EventPublished,
        title: 'Recent',
    );

    NotificationInbox::where('title', 'Recent')->update([
        'archived_at' => CarbonImmutable::now()->subDays(5),
    ]);

    $pruned = $this->service->prune(before: CarbonImmutable::now()->subDays(30));

    expect($pruned)->toBe(0);
});

test('prune uses default 90-day threshold', function (): void {
    $inbox = $this->service->create(
        recipient: $this->user,
        communication: $this->communication,
        family: NotificationFamily::EventReminder,
        priority: NotificationPriority::Normal,
        trigger: NotificationTrigger::EventPublished,
        title: 'Default Prune',
    );

    NotificationInbox::where('id', $inbox->id)->update([
        'archived_at' => CarbonImmutable::now()->subDays(95),
    ]);

    $pruned = $this->service->prune();

    expect($pruned)->toBe(1);
});

test('uses DB transactions', function (): void {
    $inbox = DB::transaction(function (): NotificationInbox {
        return $this->service->create(
            recipient: $this->user,
            communication: $this->communication,
            family: NotificationFamily::WelcomeMessage,
            priority: NotificationPriority::Low,
            trigger: NotificationTrigger::AccountCreated,
            title: 'Transactional Create',
        );
    });

    expect($inbox)->toBeInstanceOf(NotificationInbox::class);
    expect($inbox->title)->toBe('Transactional Create');
});
