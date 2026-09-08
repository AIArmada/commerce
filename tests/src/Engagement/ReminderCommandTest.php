<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationReference;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Enums\ReminderStatus;
use AIArmada\Engagement\Models\Reminder;
use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;
use Illuminate\Support\Facades\Artisan;

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
