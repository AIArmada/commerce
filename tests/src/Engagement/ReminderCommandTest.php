<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationReference;
use AIArmada\Communications\Models\Communication;
use AIArmada\Engagement\Enums\ReminderStatus;
use AIArmada\Engagement\Events\ReminderDue;
use AIArmada\Engagement\Events\ReminderFailed;
use AIArmada\Engagement\Events\ReminderSent;
use AIArmada\Engagement\Features\Reminders\Actions\ManageRemindersAction;
use AIArmada\Engagement\Features\Reminders\Api\ReadsReminderState;
use AIArmada\Engagement\Features\Reminders\Models\Reminder;
use AIArmada\Engagement\Features\Reminders\Queries\ReadDueRemindersQuery;
use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->manager = app(ManageRemindersAction::class);
    $this->recipient = engagementActor();
    $this->subject = engagementSubject();
});

it('only dispatches pending or scheduled reminders', function (): void {
    $this->manager->setReminder($this->recipient, $this->subject, 'before_start', [
        'remind_at' => now()->subMinute(),
    ]);

    $due = collect(app(ReadDueRemindersQuery::class)->execute());
    expect($due)->not->toBeEmpty();
    expect($due->first()->status)->toBeIn([ReminderStatus::Pending, ReminderStatus::Scheduled]);

    foreach ($due as $reminder) {
        $this->manager->markSent($reminder);
    }

    $noLongerDue = app(ReadDueRemindersQuery::class)->execute();
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
    Event::assertDispatched(ReminderDue::class, fn (ReminderDue $event): bool => $event->reminderId === $reminder->id);
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

            app(ManageRemindersAction::class)->setReminder($recipient, $subject, 'follow_up', [
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

it('cancels the selected reminder rather than another scheduled reminder', function (): void {
    $first = $this->manager->setReminder($this->recipient, $this->subject, 'custom', [
        'remind_at' => now()->addHour(),
    ]);
    $second = $this->manager->setReminder($this->recipient, $this->subject, 'custom', [
        'remind_at' => now()->addHours(2),
    ]);

    $this->manager->cancelRecord($second);

    expect($first->fresh()->status)->toBe(ReminderStatus::Pending)
        ->and($second->fresh()->status)->toBe(ReminderStatus::Cancelled);
});

it('evaluates expiration relative to the requested due time', function (): void {
    $reminder = $this->manager->setReminder($this->recipient, $this->subject, 'custom', [
        'remind_at' => now()->subHours(2),
        'expires_at' => now()->subHour(),
    ]);

    expect(collect(app(ReadDueRemindersQuery::class)->execute(now()->subMinutes(90)))->pluck('id')->all())->toContain($reminder->id)
        ->and(collect(app(ReadDueRemindersQuery::class)->execute())->pluck('id')->all())->not->toContain($reminder->id);
});

it('bounds due reads and excludes expired reminders from active state', function (): void {
    $expired = $this->manager->setReminder($this->recipient, $this->subject, 'expired', [
        'remind_at' => now()->subHours(2),
        'expires_at' => now()->subHour(),
    ]);
    $first = $this->manager->setReminder($this->recipient, $this->subject, 'first', [
        'remind_at' => now()->subMinutes(30),
    ]);
    $this->manager->setReminder($this->recipient, $this->subject, 'second', [
        'remind_at' => now()->subMinutes(15),
    ]);

    $due = app(ReadDueRemindersQuery::class)->execute(limit: 1);
    $state = app(ReadsReminderState::class)
        ->remindersFor($this->recipient, $this->subject);

    expect($due->pluck('id')->all())->toBe([$first->id])
        ->and(array_column($state, 'id'))->not->toContain($expired->id)
        ->and($state)->toHaveCount(2);
});

it('does not report successful reminder delivery when no delivery listener is registered', function (): void {
    $this->app->instance(Dispatcher::class, new Illuminate\Events\Dispatcher($this->app));

    $reminder = $this->manager->setReminder($this->recipient, $this->subject, 'before_start', [
        'remind_at' => now()->subMinute(),
    ]);

    expect(Artisan::call('engagement:send-due-reminders'))->toBe(0)
        ->and($reminder->fresh()->status)->toBe(ReminderStatus::Failed)
        ->and($reminder->fresh()->sent_at)->toBeNull()
        ->and($reminder->fresh()->failure_reason)->toContain('No reminder delivery listener');
});

it('enforces one pending reminder per owner, due time and channel while retaining terminal history', function (): void {
    config(['engagement.owner.enabled' => true]);
    $owner = User::factory()->create();
    $due = now()->addHour()->startOfSecond();

    foreach ([null, $owner] as $context) {
        OwnerContext::withOwner($context, function () use ($due): void {
            foreach ([null, 'mail'] as $channel) {
                $options = ['remind_at' => $due, 'channel' => $channel];
                $first = $this->manager->setReminder($this->recipient, $this->subject, 'follow_up', $options);

                expect($this->manager->setReminder($this->recipient, $this->subject, 'follow_up', $options)->id)->toBe($first->id)
                    ->and(fn () => $first->getConnection()->transaction(fn () => $first->replicate()->save()))
                    ->toThrow(UniqueConstraintViolationException::class);

                $this->manager->cancelRecord($first);
                $next = $this->manager->setReminder($this->recipient, $this->subject, 'follow_up', $options);

                expect($next->id)->not->toBe($first->id)
                    ->and($next->status)->toBe(ReminderStatus::Pending);
            }
        });
    }
});
