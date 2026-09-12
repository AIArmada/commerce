<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Services;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\Remindable;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Enums\ReminderStatus;
use AIArmada\Engagement\Events\ReminderCancelled;
use AIArmada\Engagement\Events\ReminderCreated;
use AIArmada\Engagement\Events\ReminderFailed;
use AIArmada\Engagement\Events\ReminderScheduled;
use AIArmada\Engagement\Events\ReminderSent;
use AIArmada\Engagement\Models\Reminder;
use AIArmada\Engagement\Support\EngagementModelGuard;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class DefaultReminderManager implements ReminderManager
{
    public function __construct(
        private readonly EngagementPolicyResolver $policy,
    ) {}

    public function setReminder(CanInteract $recipient, Remindable $subject, string $reminderType, array $options = []): Reminder
    {
        EngagementModelGuard::assertContract($recipient, CanInteract::class, 'recipient');
        EngagementModelGuard::assertContract($subject, Remindable::class, 'subject');

        if (! $this->policy->canSetReminder($recipient, $subject, $reminderType)) {
            throw new AuthorizationException('Setting this reminder is not authorized.');
        }

        $remindAt = $options['remind_at'] ?? null;
        $offsetMinutes = $options['offset_minutes'] ?? null;

        if ($remindAt === null && $offsetMinutes === null) {
            $remindAt = CarbonImmutable::now()->addDay();
        }

        $recipientIdentity = EngagementModelGuard::identity($recipient, 'recipient');
        $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
        $reminder = Reminder::query()->create([
            'recipient_type' => $recipientIdentity['type'],
            'recipient_id' => $recipientIdentity['id'],
            'remindable_type' => $subjectIdentity['type'],
            'remindable_id' => $subjectIdentity['id'],
            'reminder_type' => $reminderType,
            'status' => 'pending',
            'remind_at' => $remindAt,
            'offset_minutes' => $offsetMinutes,
            'anchor_type' => $options['anchor_type'] ?? null,
            'anchor_code' => $options['anchor_code'] ?? null,
            'channel' => $options['channel'] ?? null,
            'notification_class' => $options['notification_class'] ?? null,
            'scheduled_at' => $options['scheduled_at'] ?? null,
            'expires_at' => $options['expires_at'] ?? null,
            'metadata' => $options['metadata'] ?? null,
        ]);

        event(new ReminderCreated($reminder));

        if ($reminder->scheduled_at !== null) {
            event(new ReminderScheduled($reminder));
        }

        return $reminder;
    }

    public function cancelReminder(CanInteract $recipient, Remindable $subject, string $reminderType, array $options = []): void
    {
        EngagementModelGuard::assertContract($recipient, CanInteract::class, 'recipient');
        EngagementModelGuard::assertContract($subject, Remindable::class, 'subject');

        $recipientIdentity = EngagementModelGuard::identity($recipient, 'recipient');
        $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
        $reminder = Reminder::query()
            ->where('recipient_type', $recipientIdentity['type'])
            ->where('recipient_id', $recipientIdentity['id'])
            ->where('remindable_type', $subjectIdentity['type'])
            ->where('remindable_id', $subjectIdentity['id'])
            ->where('reminder_type', $reminderType)
            ->whereIn('status', ['pending', 'scheduled'])
            ->first();

        if ($reminder) {
            $reminder->update(['status' => 'cancelled', 'cancelled_at' => CarbonImmutable::now()]);
            event(new ReminderCancelled($reminder));
        }
    }

    public function dueReminders(?DateTimeInterface $at = null): iterable
    {
        $now = $at !== null ? CarbonImmutable::instance($at) : CarbonImmutable::now();

        return Reminder::query()
            ->whereIn('status', ['pending', 'scheduled'])
            ->where('remind_at', '<=', $now)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', CarbonImmutable::now());
            })
            ->orderBy('remind_at')
            ->cursor();
    }

    public function markSent(Reminder $reminder): void
    {
        $reminder = OwnerWriteGuard::findOrFailForOwner(Reminder::class, $reminder->getKey());

        DB::transaction(function () use ($reminder): void {
            $lockedReminder = Reminder::query()
                ->pending()
                ->whereKey($reminder->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedReminder instanceof Reminder) {
                return;
            }

            $lockedReminder->update(['status' => ReminderStatus::Sent, 'sent_at' => CarbonImmutable::now()]);
            event(new ReminderSent($lockedReminder));
        });
    }

    public function markFailed(Reminder $reminder, string $reason): void
    {
        $reminder = OwnerWriteGuard::findOrFailForOwner(Reminder::class, $reminder->getKey());

        DB::transaction(function () use ($reminder, $reason): void {
            $lockedReminder = Reminder::query()
                ->pending()
                ->whereKey($reminder->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedReminder instanceof Reminder) {
                return;
            }

            $lockedReminder->update([
                'status' => ReminderStatus::Failed,
                'failed_at' => CarbonImmutable::now(),
                'failure_reason' => $reason,
            ]);
            event(new ReminderFailed($lockedReminder, $reason));
        });
    }
}
