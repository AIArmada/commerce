<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Models\Reminder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->manager = app(ReminderManager::class);
    $this->recipient = new class
    {
        public function getMorphClass(): string
        {
            return 'user';
        }

        public function getKey(): string
        {
            return 'user-1';
        }
    };
    $this->subject = new class
    {
        public function getMorphClass(): string
        {
            return 'event';
        }

        public function getKey(): string
        {
            return 'event-1';
        }
    };
});

it('only dispatches pending or scheduled reminders', function (): void {
    $this->manager->setReminder($this->recipient, $this->subject, 'before_start', [
        'remind_at' => now()->subMinute(),
    ]);

    $due = $this->manager->dueReminders();
    expect($due)->not->toBeEmpty();
    expect($due->first()->status)->toBeIn(['pending', 'scheduled']);

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
        'status' => Reminder::STATUS_PENDING,
        'remind_at' => now()->subMinute(),
    ]);

    $this->manager->markSent($reminder);

    expect($reminder->fresh()->status)->toBe(Reminder::STATUS_SENT);
    expect($reminder->fresh()->sent_at)->not->toBeNull();
});

it('marks failed reminders with failure_reason', function (): void {
    $reminder = Reminder::factory()->create([
        'remindable_type' => 'event',
        'remindable_id' => 'event-1',
        'recipient_type' => 'user',
        'recipient_id' => 'user-1',
        'reminder_type' => 'before_start',
        'status' => Reminder::STATUS_PENDING,
        'remind_at' => now()->subMinute(),
    ]);

    $this->manager->markFailed($reminder, 'Channel unavailable');

    expect($reminder->fresh()->status)->toBe(Reminder::STATUS_FAILED);
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
            app(ReminderManager::class)->setReminder($owner, $owner, 'follow_up', [
                'remind_at' => now()->subMinute(),
            ]);
        });
    }

    expect(Artisan::call('engagement:send-due-reminders'))->toBe(0)
        ->and(Reminder::query()->withoutOwnerScope()->where('status', Reminder::STATUS_SENT)->count())
        ->toBe(2);
});
