<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Models\Reminder;

beforeEach(function () {
    $this->manager = app(ReminderManager::class);
    $this->recipient = new class {
        public function getMorphClass(): string { return 'user'; }
        public function getKey(): string { return 'user-1'; }
    };
    $this->subject = new class {
        public function getMorphClass(): string { return 'event'; }
        public function getKey(): string { return 'event-1'; }
    };
});

it('only dispatches pending or scheduled reminders', function () {
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

it('marks sent reminders with sent_at', function () {
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

it('marks failed reminders with failure_reason', function () {
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
