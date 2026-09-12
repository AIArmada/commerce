<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationReference;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Enums\ReminderStatus;
use AIArmada\Engagement\Events\ReminderDue;
use AIArmada\Engagement\Events\ReminderFailed;
use AIArmada\Engagement\Events\ReminderSent;
use AIArmada\Engagement\Models\Reminder;
use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->manager = app(ReminderManager::class);
    $this->recipient = new EngagementActor;
    $this->subject = new EngagementSubject;
});

it('only dispatches pending or scheduled reminders', function (): void {
    $this->manager->setReminder($this->recipient, $this->subject, 'before_start', [
        'remind_at' => now()->subMinute(),
    ]);

    $due = collect($this->manager->dueReminders());
    expect($due)->not->toBeEmpty();
    expect($due->first()->status)->toBeIn([ReminderStatus::Pending, ReminderStatus::Scheduled]);

    foreach ($due as $reminder) {
        $this->manager->markSent($reminder);
    }

    $noLongerDue = $this->manager->dueReminders();
    expect($noLongerDue)->toBeEmpty();
});

it('marks sent reminders with sent_at', function (): void {
    $reminder = Reminder::factory()->create([
        'remindable_type' => 'event',
        'remindable_id' => 'event-1',
        'recipient_type' => 'user',
        'recipient_id' => 'user-1',
        'reminder_type' => 'before_start',
        'status' => ReminderStatus::Pending,
        'remind_at' => now()->subMinute(),
    ]);

    $this->manager->markSent($reminder);

    expect($reminder->fresh()->status)->toBe(ReminderStatus::Sent);
    expect($reminder->fresh()->sent_at)->not->toBeNull();
});

it('marks failed reminders with failure_reason', function (): void {
    $reminder = Reminder::factory()->create([
        'remindable_type' => 'event',
        'remindable_id' => 'event-1',
        'recipient_type' => 'user',
        'recipient_id' => 'user-1',
        'reminder_type' => 'before_start',
        'status' => ReminderStatus::Pending,
        'remind_at' => now()->subMinute(),
    ]);

    $this->manager->markFailed($reminder, 'Channel unavailable');

    expect($reminder->fresh()->status)->toBe(ReminderStatus::Failed);
    expect($reminder->fresh()->failure_reason)->toBe('Channel unavailable');
});

it('does not transition a terminal reminder again', function (): void {
    Event::fake([ReminderSent::class, ReminderFailed::class]);

    $reminder = Reminder::factory()->create([
        'remindable_type' => 'event',
        'remindable_id' => 'event-1',
        'recipient_type' => 'user',
        'recipient_id' => 'user-1',
        'reminder_type' => 'before_start',
        'status' => ReminderStatus::Pending,
        'remind_at' => now()->subMinute(),
    ]);

    $this->manager->markSent($reminder);
    $this->manager->markFailed($reminder, 'Late failure');

    $freshReminder = $reminder->fresh();

    expect($freshReminder->status)->toBe(ReminderStatus::Sent)
        ->and($freshReminder->sent_at)->not->toBeNull()
        ->and($freshReminder->failed_at)->toBeNull()
        ->and($freshReminder->failure_reason)->toBeNull();

    Event::assertDispatchedTimes(ReminderSent::class, 1);
    Event::assertNotDispatched(ReminderFailed::class);
});

it('dispatches a due reminder only once across command runs', function (): void {
    Event::fake([ReminderDue::class]);

    $reminder = Reminder::factory()->create([
        'remindable_type' => 'event',
        'remindable_id' => 'event-1',
        'recipient_type' => 'user',
        'recipient_id' => 'user-1',
        'reminder_type' => 'before_start',
        'status' => ReminderStatus::Pending,
        'remind_at' => now()->subMinute(),
    ]);

    expect(Artisan::call('engagement:send-due-reminders'))->toBe(0)
        ->and(Artisan::call('engagement:send-due-reminders'))->toBe(0);

    Event::assertDispatchedTimes(ReminderDue::class, 1);
    expect($reminder->fresh()->status)->toBe(ReminderStatus::Sent);
});

it('processes due reminders across owners', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Reminder Owner A',
        'email' => 'reminder-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Reminder Owner B',
        'email' => 'reminder-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    foreach ([$ownerA, $ownerB] as $owner) {
        OwnerContext::withOwner($owner, function () use ($owner): void {
            $recipient = EngagementActor::query()->create([
                'name' => 'Reminder Recipient ' . $owner->getKey(),
                'email' => 'reminder-recipient-' . $owner->getKey() . '@example.com',
                'password' => 'secret',
            ]);
            $subject = EngagementSubject::query()->create([
                'name' => 'Reminder Subject ' . $owner->getKey(),
                'email' => 'reminder-subject-' . $owner->getKey() . '@example.com',
                'password' => 'secret',
            ]);

            app(ReminderManager::class)->setReminder($recipient, $subject, 'follow_up', [
                'remind_at' => now()->subMinute(),
            ]);
        });
    }

    expect(Artisan::call('engagement:send-due-reminders'))->toBe(0)
        ->and(Reminder::query()->withoutOwnerScope()->where('status', ReminderStatus::Sent)->count())
        ->toBe(2)
        ->and(Communication::query()->withoutOwnerScope()->where('purpose', 'engagement.reminder')->count())
        ->toBe(2)
        ->and(CommunicationReference::query()->withoutOwnerScope()->where('role', 'engagement_reminder')->count())
        ->toBe(2);
});
