<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationReference;
use AIArmada\Engagement\Enums\ReminderStatus;
use AIArmada\Engagement\Events\ReminderDue;
use AIArmada\Engagement\Features\Reminders\Actions\ManageRemindersAction;
use AIArmada\Engagement\Features\Reminders\EntryPoints\Listeners\DispatchReminderThroughCommunications;
use AIArmada\Engagement\Features\Reminders\Models\Reminder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->manager = app(ManageRemindersAction::class);
    $this->recipient = engagementActor();
    $this->subject = engagementSubject();
});

it('claims a due reminder once and fences stale finalization', function (): void {
    $reminder = $this->manager->setReminder($this->recipient, $this->subject, 'before_start', [
        'remind_at' => CarbonImmutable::now()->subMinute(),
    ]);
    $now = CarbonImmutable::now();
    $firstToken = (string) Str::uuid();
    $secondToken = (string) Str::uuid();

    expect($this->manager->claimForDelivery($reminder, $now, $firstToken, $now->addMinutes(5), 3))->toBeTrue()
        ->and($this->manager->claimForDelivery($reminder, $now, $secondToken, $now->addMinutes(5), 3))->toBeFalse();

    $claimed = $reminder->fresh();

    expect($claimed->claim_token)->toBe($firstToken)
        ->and($claimed->claimed_at)->not->toBeNull()
        ->and($claimed->claim_expires_at)->not->toBeNull()
        ->and($claimed->attempts)->toBe(1);

    $claimed->forceFill(['claim_expires_at' => $now->subSecond()])->save();

    expect($this->manager->claimForDelivery($claimed, $now, $secondToken, $now->addMinutes(5), 3))->toBeTrue()
        ->and($this->manager->completeClaim($claimed, $firstToken))->toBeFalse()
        ->and($this->manager->failClaim($claimed, $firstToken, 'stale worker'))->toBeFalse()
        ->and($reminder->fresh()->status)->toBe(ReminderStatus::Pending)
        ->and($reminder->fresh()->claim_token)->toBe($secondToken)
        ->and($this->manager->completeClaim($reminder->fresh(), $secondToken))->toBeTrue()
        ->and($reminder->fresh()->status)->toBe(ReminderStatus::Sent)
        ->and($reminder->fresh()->sent_at)->not->toBeNull()
        ->and($reminder->fresh()->claim_token)->toBeNull()
        ->and($reminder->fresh()->attempts)->toBe(2);
});

it('lets cancellation win over an in-flight claim', function (): void {
    $reminder = $this->manager->setReminder($this->recipient, $this->subject, 'before_start', [
        'remind_at' => CarbonImmutable::now()->subMinute(),
    ]);
    $now = CarbonImmutable::now();
    $token = (string) Str::uuid();

    expect($this->manager->claimForDelivery($reminder, $now, $token, $now->addMinutes(5), 3))->toBeTrue();

    $this->manager->cancelRecord($reminder);

    expect($this->manager->completeClaim($reminder, $token))->toBeFalse()
        ->and($reminder->fresh()->status)->toBe(ReminderStatus::Cancelled)
        ->and($reminder->fresh()->sent_at)->toBeNull()
        ->and($reminder->fresh()->claim_token)->toBeNull();
});

it('recovers an expired claim and stops after the attempt limit', function (): void {
    $reminder = $this->manager->setReminder($this->recipient, $this->subject, 'before_start', [
        'remind_at' => CarbonImmutable::now()->subMinute(),
    ]);
    $now = CarbonImmutable::now();

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $token = (string) Str::uuid();

        expect($this->manager->claimForDelivery($reminder, $now, $token, $now->addMinutes(5), 3))->toBeTrue()
            ->and($reminder->fresh()->attempts)->toBe($attempt);

        $reminder->refresh()->forceFill(['claim_expires_at' => $now->subSecond()])->save();
    }

    expect($this->manager->claimForDelivery($reminder, $now, (string) Str::uuid(), $now->addMinutes(5), 3))->toBeFalse()
        ->and($this->manager->failExhaustedClaim($reminder, $now, 3))->toBeTrue()
        ->and($reminder->fresh()->status)->toBe(ReminderStatus::Failed)
        ->and($reminder->fresh()->failure_reason)->toContain('attempts exhausted')
        ->and($reminder->fresh()->failed_at)->not->toBeNull()
        ->and($reminder->fresh()->claim_token)->toBeNull();
});

it('does not claim expired reminders', function (): void {
    $reminder = $this->manager->setReminder($this->recipient, $this->subject, 'before_start', [
        'remind_at' => CarbonImmutable::now()->subHours(2),
        'expires_at' => CarbonImmutable::now()->subHour(),
    ]);

    expect($this->manager->claimForDelivery($reminder, CarbonImmutable::now(), (string) Str::uuid(), CarbonImmutable::now()->addMinutes(5), 3))->toBeFalse()
        ->and($reminder->fresh()->status)->toBe(ReminderStatus::Pending)
        ->and($reminder->fresh()->attempts)->toBe(0);
});

it('skips a reminder claimed by a cooperating worker until its lease expires', function (): void {
    Event::fake([ReminderDue::class]);

    $reminder = $this->manager->setReminder($this->recipient, $this->subject, 'before_start', [
        'remind_at' => CarbonImmutable::now()->subMinute(),
    ]);
    $now = CarbonImmutable::now();
    $token = (string) Str::uuid();

    expect($this->manager->claimForDelivery($reminder, $now, $token, $now->addMinutes(5), 3))->toBeTrue()
        ->and(Artisan::call('engagement:send-due-reminders'))->toBe(0)
        ->and($reminder->fresh()->status)->toBe(ReminderStatus::Pending);

    Event::assertNotDispatched(ReminderDue::class);

    $reminder->forceFill(['claim_expires_at' => $now->subSecond()])->save();

    expect(Artisan::call('engagement:send-due-reminders'))->toBe(0)
        ->and($reminder->fresh()->status)->toBe(ReminderStatus::Sent)
        ->and($reminder->fresh()->attempts)->toBe(2);

    Event::assertDispatchedTimes(ReminderDue::class, 1);
});

it('dispatches due reminders outside the finalization transaction', function (): void {
    $observed = null;
    $baselineLevel = DB::transactionLevel();

    Event::listen(ReminderDue::class, function (ReminderDue $event) use (&$observed): void {
        $reminder = Reminder::query()->whereKey($event->reminderId)->first();

        $observed = [
            'transaction_level' => DB::transactionLevel(),
            'status' => $reminder?->status,
            'claim_token' => $reminder?->claim_token,
        ];
    });

    $reminder = $this->manager->setReminder($this->recipient, $this->subject, 'before_start', [
        'remind_at' => CarbonImmutable::now()->subMinute(),
    ]);

    expect(Artisan::call('engagement:send-due-reminders'))->toBe(0)
        ->and($observed)->not->toBeNull()
        ->and($observed['transaction_level'])->toBe($baselineLevel)
        ->and($observed['status'])->toBe(ReminderStatus::Pending)
        ->and($observed['claim_token'])->not->toBeNull()
        ->and($reminder->fresh()->status)->toBe(ReminderStatus::Sent);
});

it('reuses an existing communication when a reminder delivery is retried', function (): void {
    $reminder = $this->manager->setReminder($this->recipient, $this->subject, 'before_start', [
        'remind_at' => CarbonImmutable::now()->subMinute(),
    ]);
    $event = new ReminderDue(
        $reminder->id,
        $reminder->recipient_type,
        $reminder->recipient_id,
        $reminder->remindable_type,
        $reminder->remindable_id,
        $reminder->owner_type,
        $reminder->owner_id,
        $reminder->reminder_type,
    );

    app(DispatchReminderThroughCommunications::class)->handle($event);
    app(DispatchReminderThroughCommunications::class)->handle($event);

    expect(Communication::query()->withoutOwnerScope()
        ->where('idempotency_key', 'engagement-reminder:' . $reminder->id)
        ->count())->toBe(1)
        ->and(CommunicationReference::query()->withoutOwnerScope()
            ->where('reference_type', Reminder::class)
            ->where('reference_id', $reminder->id)
            ->where('role', 'engagement_reminder')
            ->count())->toBe(1);
});

it('rejects foreign owner claim operations', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $reminder = OwnerContext::withOwner($ownerB, fn (): Reminder => $this->manager->setReminder($this->recipient, $this->subject, 'before_start', [
        'remind_at' => CarbonImmutable::now()->subMinute(),
    ]));

    OwnerContext::withOwner($ownerA, function () use ($reminder): void {
        $now = CarbonImmutable::now();
        $token = (string) Str::uuid();

        expect(fn (): bool => $this->manager->claimForDelivery($reminder, $now, $token, $now->addMinutes(5), 3))
            ->toThrow(AuthorizationException::class)
            ->and(fn (): bool => $this->manager->completeClaim($reminder, $token))
            ->toThrow(AuthorizationException::class)
            ->and(fn (): bool => $this->manager->failClaim($reminder, $token, 'spoofed'))
            ->toThrow(AuthorizationException::class)
            ->and(fn (): bool => $this->manager->releaseClaim($reminder, $token))
            ->toThrow(AuthorizationException::class)
            ->and(fn (): bool => $this->manager->failExhaustedClaim($reminder, $now, 3))
            ->toThrow(AuthorizationException::class);
    });

    $fresh = OwnerContext::withOwner($ownerB, fn (): Reminder => $reminder->fresh());

    expect($fresh->status)->toBe(ReminderStatus::Pending)
        ->and($fresh->attempts)->toBe(0);
});

it('keeps direct terminal transitions outside the delivery claim', function (): void {
    $reminder = $this->manager->setReminder($this->recipient, $this->subject, 'before_start', [
        'remind_at' => CarbonImmutable::now()->subMinute(),
    ]);
    $now = CarbonImmutable::now();
    $token = (string) Str::uuid();

    expect($this->manager->claimForDelivery($reminder, $now, $token, $now->addMinutes(5), 3))->toBeTrue();

    $this->manager->markSent($reminder);

    expect($reminder->fresh()->status)->toBe(ReminderStatus::Sent)
        ->and($reminder->fresh()->claim_token)->toBeNull()
        ->and($this->manager->completeClaim($reminder, $token))->toBeFalse();
});
