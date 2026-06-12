<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Services;

use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Events\ReminderCancelled;
use AIArmada\Engagement\Events\ReminderCreated;
use AIArmada\Engagement\Events\ReminderFailed;
use AIArmada\Engagement\Events\ReminderScheduled;
use AIArmada\Engagement\Events\ReminderSent;
use AIArmada\Engagement\Models\Reminder;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class DefaultReminderManager implements ReminderManager
{
    public function __construct(
        private readonly EngagementPolicyResolver $policy,
    ) {}

    public function setReminder(mixed $recipient, mixed $subject, string $reminderType, array $options = []): Reminder
    {
        $this->policy->canSetReminder($recipient, $subject, $reminderType);

        $remindAt = $options['remind_at'] ?? null;
        $offsetMinutes = $options['offset_minutes'] ?? null;

        if ($remindAt === null && $offsetMinutes === null) {
            $remindAt = CarbonImmutable::now()->addDay();
        }

        $reminder = Reminder::query()->create([
            'recipient_type' => $recipient->getMorphClass(),
            'recipient_id' => $recipient->getKey(),
            'remindable_type' => $subject->getMorphClass(),
            'remindable_id' => $subject->getKey(),
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

    public function cancelReminder(mixed $recipient, mixed $subject, string $reminderType, array $options = []): void
    {
        $reminder = Reminder::query()
            ->where('recipient_type', $recipient->getMorphClass())
            ->where('recipient_id', $recipient->getKey())
            ->where('remindable_type', $subject->getMorphClass())
            ->where('remindable_id', $subject->getKey())
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
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', CarbonImmutable::now());
            })
            ->orderBy('remind_at')
            ->cursor();
    }

    public function markSent(Reminder $reminder): void
    {
        $reminder->update(['status' => 'sent', 'sent_at' => CarbonImmutable::now()]);
        event(new ReminderSent($reminder));
    }

    public function markFailed(Reminder $reminder, string $reason): void
    {
        $reminder->update([
            'status' => 'failed',
            'failed_at' => CarbonImmutable::now(),
            'failure_reason' => $reason,
        ]);
        event(new ReminderFailed($reminder, $reason));
    }
}
