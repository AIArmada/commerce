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
use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DefaultReminderManager implements ReminderManager
{
    public function __construct(
        private readonly EngagementPolicyResolver $policy,
    ) {}

    public function setReminder(CanInteract $recipient, Remindable $subject, string $reminderType, array $options = []): Reminder
    {
        EngagementModelGuard::assertContract($recipient, CanInteract::class, 'recipient');
        EngagementModelGuard::assertContract($subject, Remindable::class, 'subject');

        $reminderType = EngagementModelGuard::requiredString($reminderType, 'reminder_type');
        $channel = EngagementModelGuard::boundedString($options['channel'] ?? null, 'channel');
        $anchorType = EngagementModelGuard::boundedString($options['anchor_type'] ?? null, 'anchor_type');
        $anchorCode = EngagementModelGuard::boundedString($options['anchor_code'] ?? null, 'anchor_code');
        $metadata = EngagementModelGuard::optionalArray($options['metadata'] ?? null, 'metadata');
        $notificationClass = $this->resolveNotificationClass($options['notification_class'] ?? null);
        $offsetMinutes = EngagementModelGuard::offsetMinutes($options['offset_minutes'] ?? null);
        $remindAt = $this->resolveRemindAt($subject, $options, $offsetMinutes);

        if (! $this->policy->canSetReminder($recipient, $subject, $reminderType)) {
            throw new AuthorizationException('Setting this reminder is not authorized.');
        }

        $recipientIdentity = EngagementModelGuard::identity($recipient, 'recipient');
        $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');

        return DB::transaction(function () use (
            $recipientIdentity,
            $subjectIdentity,
            $reminderType,
            $remindAt,
            $offsetMinutes,
            $anchorType,
            $anchorCode,
            $channel,
            $notificationClass,
            $options,
            $metadata,
        ): Reminder {
            $existing = Reminder::query()
                ->where('recipient_type', $recipientIdentity['type'])
                ->where('recipient_id', $recipientIdentity['id'])
                ->where('remindable_type', $subjectIdentity['type'])
                ->where('remindable_id', $subjectIdentity['id'])
                ->where('reminder_type', $reminderType)
                ->where('channel', $channel)
                ->where('remind_at', $remindAt)
                ->whereIn('status', ['pending', 'scheduled'])
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Reminder) {
                return $existing;
            }

            $reminder = new Reminder([
                'recipient_type' => $recipientIdentity['type'],
                'recipient_id' => $recipientIdentity['id'],
                'remindable_type' => $subjectIdentity['type'],
                'remindable_id' => $subjectIdentity['id'],
                'reminder_type' => $reminderType,
                'status' => 'pending',
                'remind_at' => $remindAt,
                'offset_minutes' => $offsetMinutes,
                'anchor_type' => $anchorType,
                'anchor_code' => $anchorCode,
                'channel' => $channel,
                'scheduled_at' => $options['scheduled_at'] ?? null,
                'expires_at' => $options['expires_at'] ?? null,
                'metadata' => $metadata,
            ]);

            if ($notificationClass !== null) {
                $reminder->notification_class = $notificationClass;
            }

            $reminder->save();

            event(new ReminderCreated($reminder));

            if ($reminder->scheduled_at !== null) {
                event(new ReminderScheduled($reminder));
            }

            return $reminder;
        });
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

    /**
     * @param  array<string|int, mixed>  $options
     */
    private function resolveRemindAt(Remindable $subject, array $options, ?int $offsetMinutes): CarbonImmutable
    {
        $remindAt = $options['remind_at'] ?? null;

        if ($remindAt !== null && $offsetMinutes !== null) {
            throw new InvalidArgumentException('Provide either remind_at or offset_minutes for a reminder, not both.');
        }

        if ($remindAt !== null) {
            return $this->toImmutableRemindAt($remindAt);
        }

        if ($offsetMinutes !== null) {
            $anchorType = EngagementModelGuard::boundedString($options['anchor_type'] ?? null, 'anchor_type');

            if ($anchorType === null || $anchorType === '') {
                throw new InvalidArgumentException('Reminders with offset_minutes require an anchor_type.');
            }

            $anchorCode = EngagementModelGuard::boundedString($options['anchor_code'] ?? null, 'anchor_code');
            $anchor = $subject->reminderAnchorTime($anchorType, $anchorCode);

            if (! $anchor instanceof DateTimeInterface) {
                throw new InvalidArgumentException(sprintf('Reminder anchor [%s] did not resolve to a time.', $anchorType));
            }

            return CarbonImmutable::instance($anchor)->subMinutes($offsetMinutes)->startOfSecond();
        }

        return CarbonImmutable::now()->addDay()->startOfMinute();
    }

    private function toImmutableRemindAt(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value->startOfSecond();
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfSecond();
        }

        if (is_string($value) && $value !== '') {
            try {
                return CarbonImmutable::parse($value)->startOfSecond();
            } catch (InvalidFormatException) {
                throw new InvalidArgumentException('Reminder remind_at must be a valid datetime.');
            }
        }

        throw new InvalidArgumentException('Reminder remind_at must be a valid datetime.');
    }

    private function resolveNotificationClass(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || $value === '' || mb_strlen($value) > EngagementModelGuard::STRING_MAX_LENGTH || ! class_exists($value) || ! is_a($value, Notification::class, true)) {
            throw new InvalidArgumentException('The reminder notification class must be an existing Laravel Notification class name.');
        }

        $allowed = config('engagement.notifications.allowed', []);

        if (! is_array($allowed) || ! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('Notification class [%s] is not in the engagement.notifications.allowed allowlist.', $value));
        }

        return $value;
    }
}
