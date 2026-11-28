<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Reminders\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\Remindable;
use AIArmada\Engagement\Enums\ReminderStatus;
use AIArmada\Engagement\Events\ReminderCancelled;
use AIArmada\Engagement\Events\ReminderCreated;
use AIArmada\Engagement\Events\ReminderFailed;
use AIArmada\Engagement\Events\ReminderScheduled;
use AIArmada\Engagement\Events\ReminderSent;
use AIArmada\Engagement\Features\Reminders\Api\WritesReminderRecords;
use AIArmada\Engagement\Features\Reminders\Models\Reminder;
use AIArmada\Engagement\Support\EngagementModelGuard;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ManageRemindersAction implements WritesReminderRecords
{
    public function __construct(
        private readonly EngagementPolicyResolver $policy,
    ) {}

    public function setReminder(CanInteract $recipient, Remindable $subject, string $reminderType, array $options = []): Reminder
    {
        EngagementModelGuard::assertContract($recipient, CanInteract::class, 'recipient');
        EngagementModelGuard::assertContract($subject, Remindable::class, 'subject');
        EngagementModelGuard::assertWritableIdentity($recipient, 'recipient');
        EngagementModelGuard::assertWritableIdentity($subject, 'subject');

        $reminderType = EngagementModelGuard::requiredString($reminderType, 'reminder_type');
        $channel = EngagementModelGuard::boundedString($options['channel'] ?? null, 'channel');
        $anchorType = EngagementModelGuard::boundedString($options['anchor_type'] ?? null, 'anchor_type');
        $anchorCode = EngagementModelGuard::boundedString($options['anchor_code'] ?? null, 'anchor_code');
        $metadata = EngagementModelGuard::optionalArray($options['metadata'] ?? null, 'metadata');
        $notificationClass = $this->resolveNotificationClass($options['notification_class'] ?? null);
        $offsetMinutes = EngagementModelGuard::offsetMinutes($options['offset_minutes'] ?? null);
        $remindAt = $this->resolveRemindAt($subject, $options, $offsetMinutes);

        $scheduledAt = isset($options['scheduled_at']) ? $this->toImmutableRemindAt($options['scheduled_at']) : null;
        $expiresAt = isset($options['expires_at']) ? $this->toImmutableRemindAt($options['expires_at']) : null;

        if ($expiresAt !== null && $expiresAt->lessThanOrEqualTo($remindAt)) {
            throw new InvalidArgumentException('Reminder expiration must follow its due time.');
        }

        if (! $this->policy->canSetReminder($recipient, $subject, $reminderType)) {
            throw new AuthorizationException('Setting this reminder is not authorized.');
        }

        $recipientIdentity = EngagementModelGuard::identity($recipient, 'recipient');
        $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');

        return (new Reminder)->getConnection()->transaction(function () use (
            $recipient,
            $recipientIdentity,
            $subjectIdentity,
            $reminderType,
            $remindAt,
            $offsetMinutes,
            $anchorType,
            $anchorCode,
            $channel,
            $notificationClass,
            $scheduledAt,
            $expiresAt,
            $metadata,
        ): Reminder {
            if ($recipient instanceof Model && $recipient->exists) {
                $recipient->newQuery()->whereKey($recipient->getKey())->lockForUpdate()->firstOrFail();
            }

            $existing = $this->matchingReminder($recipientIdentity, $subjectIdentity, $reminderType, $remindAt, $channel)
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
                'status' => $scheduledAt === null ? ReminderStatus::Pending : ReminderStatus::Scheduled,
                'remind_at' => $remindAt,
                'offset_minutes' => $offsetMinutes,
                'anchor_type' => $anchorType,
                'anchor_code' => $anchorCode,
                'channel' => $channel,
                'scheduled_at' => $scheduledAt,
                'expires_at' => $expiresAt,
                'metadata' => $metadata,
            ]);

            if ($notificationClass !== null) {
                $reminder->notification_class = $notificationClass;
            }

            try {
                // A nested transaction provides a savepoint on PostgreSQL.
                $reminder->getConnection()->transaction(fn () => $reminder->save());
            } catch (UniqueConstraintViolationException $exception) {
                $existing = $this->matchingReminder($recipientIdentity, $subjectIdentity, $reminderType, $remindAt, $channel)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof Reminder) {
                    return $existing;
                }

                throw $exception;
            }

            event(new ReminderCreated(
                $reminder->id,
                $reminder->recipient_type,
                $reminder->recipient_id,
                $reminder->remindable_type,
                $reminder->remindable_id,
                $reminder->owner_type,
                $reminder->owner_id,
                $reminder->reminder_type,
            ));

            if ($reminder->scheduled_at !== null) {
                event(new ReminderScheduled(
                    $reminder->id,
                    $reminder->recipient_type,
                    $reminder->recipient_id,
                    $reminder->remindable_type,
                    $reminder->remindable_id,
                    $reminder->owner_type,
                    $reminder->owner_id,
                    $reminder->reminder_type,
                ));
            }

            return $reminder;
        });
    }

    /**
     * @param  array{type: string, id: string}  $recipient
     * @param  array{type: string, id: string}  $subject
     * @return Builder<Reminder>
     */
    private function matchingReminder(array $recipient, array $subject, string $type, CarbonImmutable $due, ?string $channel): Builder
    {
        return Reminder::query()->forOwner()
            ->where('recipient_type', $recipient['type'])
            ->where('recipient_id', $recipient['id'])
            ->where('remindable_type', $subject['type'])
            ->where('remindable_id', $subject['id'])
            ->where('reminder_type', $type)
            ->where('channel', $channel)
            ->where('remind_at', $due)
            ->pending();
    }

    public function cancelReminder(CanInteract $recipient, Remindable $subject, string $reminderType, array $options = []): void
    {
        EngagementModelGuard::assertContract($recipient, CanInteract::class, 'recipient');
        EngagementModelGuard::assertContract($subject, Remindable::class, 'subject');
        EngagementModelGuard::assertWritableIdentity($recipient, 'recipient');
        EngagementModelGuard::assertWritableIdentity($subject, 'subject');

        $recipientIdentity = EngagementModelGuard::identity($recipient, 'recipient');
        $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
        $reminderType = EngagementModelGuard::requiredString($reminderType, 'reminder_type');
        $channel = EngagementModelGuard::boundedString($options['channel'] ?? null, 'channel');
        $remindAt = isset($options['remind_at']) ? $this->toImmutableRemindAt($options['remind_at']) : null;

        (new Reminder)->getConnection()->transaction(function () use ($recipientIdentity, $subjectIdentity, $reminderType, $options, $channel, $remindAt): void {
            Reminder::query()->forOwner()
                ->where('recipient_type', $recipientIdentity['type'])
                ->where('recipient_id', $recipientIdentity['id'])
                ->where('remindable_type', $subjectIdentity['type'])
                ->where('remindable_id', $subjectIdentity['id'])
                ->where('reminder_type', $reminderType)
                ->when(array_key_exists('channel', $options), fn ($query) => $query->where('channel', $channel))
                ->when($remindAt !== null, fn ($query) => $query->where('remind_at', $remindAt))
                ->pending()
                ->chunkById(100, function ($records): void {
                    foreach ($records as $record) {
                        $this->cancelRecord($record);
                    }
                });
        });
    }

    public function cancelRecord(Reminder $reminder): void
    {
        $this->guardOwnerWrite($reminder);

        $reminder->getConnection()->transaction(function () use ($reminder): void {
            $locked = Reminder::query()->pending()->whereKey($reminder->getKey())->lockForUpdate()->first();

            if ($locked instanceof Reminder) {
                $locked->forceFill([
                    'status' => ReminderStatus::Cancelled,
                    'cancelled_at' => CarbonImmutable::now(),
                    'claim_token' => null,
                    'claimed_at' => null,
                    'claim_expires_at' => null,
                ])->save();
                event(new ReminderCancelled(
                    $locked->id,
                    $locked->recipient_type,
                    $locked->recipient_id,
                    $locked->remindable_type,
                    $locked->remindable_id,
                    $locked->owner_type,
                    $locked->owner_id,
                    $locked->reminder_type,
                ));
            }
        });
    }

    public function markSent(Reminder $reminder): void
    {
        $this->guardOwnerWrite($reminder);

        (new Reminder)->getConnection()->transaction(function () use ($reminder): void {
            $lockedReminder = Reminder::query()
                ->pending()
                ->whereKey($reminder->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedReminder instanceof Reminder) {
                return;
            }

            $lockedReminder->forceFill([
                'status' => ReminderStatus::Sent,
                'sent_at' => CarbonImmutable::now(),
                'claim_token' => null,
                'claimed_at' => null,
                'claim_expires_at' => null,
            ])->save();
            event(new ReminderSent(
                $lockedReminder->id,
                $lockedReminder->recipient_type,
                $lockedReminder->recipient_id,
                $lockedReminder->remindable_type,
                $lockedReminder->remindable_id,
                $lockedReminder->owner_type,
                $lockedReminder->owner_id,
                $lockedReminder->reminder_type,
            ));
        });
    }

    public function markFailed(Reminder $reminder, string $reason): void
    {
        $this->guardOwnerWrite($reminder);

        (new Reminder)->getConnection()->transaction(function () use ($reminder, $reason): void {
            $lockedReminder = Reminder::query()
                ->pending()
                ->whereKey($reminder->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedReminder instanceof Reminder) {
                return;
            }

            $lockedReminder->forceFill([
                'status' => ReminderStatus::Failed,
                'failed_at' => CarbonImmutable::now(),
                'failure_reason' => $reason,
                'claim_token' => null,
                'claimed_at' => null,
                'claim_expires_at' => null,
            ])->save();
            event(new ReminderFailed(
                $lockedReminder->id,
                $lockedReminder->recipient_type,
                $lockedReminder->recipient_id,
                $lockedReminder->remindable_type,
                $lockedReminder->remindable_id,
                $lockedReminder->owner_type,
                $lockedReminder->owner_id,
                $lockedReminder->reminder_type,
                $reason,
            ));
        });
    }

    public function claimForDelivery(
        Reminder $reminder,
        CarbonImmutable $now,
        string $claimToken,
        CarbonImmutable $claimExpiresAt,
        int $maxAttempts,
    ): bool {
        $this->guardOwnerWrite($reminder);

        if ($maxAttempts < 1 || $claimToken === '' || $claimExpiresAt->lessThanOrEqualTo($now)) {
            return false;
        }

        return Reminder::query()->forOwner()
            ->whereKey($reminder->getKey())
            ->pending()
            ->whereNotNull('remind_at')
            ->where('remind_at', '<=', $now)
            ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->where(fn (Builder $query): Builder => $query->whereNull('claim_expires_at')->orWhere('claim_expires_at', '<=', $now))
            ->where('attempts', '<', $maxAttempts)
            ->update([
                'claim_token' => $claimToken,
                'claimed_at' => $now,
                'claim_expires_at' => $claimExpiresAt,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => $now,
            ]) === 1;
    }

    public function completeClaim(Reminder $reminder, string $claimToken): bool
    {
        $this->guardOwnerWrite($reminder);

        if ($claimToken === '') {
            return false;
        }

        return (new Reminder)->getConnection()->transaction(function () use ($reminder, $claimToken): bool {
            $now = CarbonImmutable::now();
            $updated = Reminder::query()->forOwner()
                ->whereKey($reminder->getKey())
                ->pending()
                ->where('claim_token', $claimToken)
                ->update([
                    'status' => ReminderStatus::Sent,
                    'sent_at' => $now,
                    'claim_token' => null,
                    'claimed_at' => null,
                    'claim_expires_at' => null,
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                return false;
            }

            event(new ReminderSent(
                $reminder->id,
                $reminder->recipient_type,
                $reminder->recipient_id,
                $reminder->remindable_type,
                $reminder->remindable_id,
                $reminder->owner_type,
                $reminder->owner_id,
                $reminder->reminder_type,
            ));

            return true;
        });
    }

    public function failClaim(Reminder $reminder, string $claimToken, string $reason): bool
    {
        $this->guardOwnerWrite($reminder);

        if ($claimToken === '') {
            return false;
        }

        return (new Reminder)->getConnection()->transaction(function () use ($reminder, $claimToken, $reason): bool {
            $now = CarbonImmutable::now();
            $updated = Reminder::query()->forOwner()
                ->whereKey($reminder->getKey())
                ->pending()
                ->where('claim_token', $claimToken)
                ->update([
                    'status' => ReminderStatus::Failed,
                    'failed_at' => $now,
                    'failure_reason' => $reason,
                    'claim_token' => null,
                    'claimed_at' => null,
                    'claim_expires_at' => null,
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                return false;
            }

            event(new ReminderFailed(
                $reminder->id,
                $reminder->recipient_type,
                $reminder->recipient_id,
                $reminder->remindable_type,
                $reminder->remindable_id,
                $reminder->owner_type,
                $reminder->owner_id,
                $reminder->reminder_type,
                $reason,
            ));

            return true;
        });
    }

    public function failExhaustedClaim(Reminder $reminder, CarbonImmutable $now, int $maxAttempts): bool
    {
        $this->guardOwnerWrite($reminder);

        if ($maxAttempts < 1) {
            return false;
        }

        return (new Reminder)->getConnection()->transaction(function () use ($reminder, $now, $maxAttempts): bool {
            $reason = 'Reminder delivery attempts exhausted.';
            $updated = Reminder::query()->forOwner()
                ->whereKey($reminder->getKey())
                ->pending()
                ->whereNotNull('remind_at')
                ->where('remind_at', '<=', $now)
                ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
                ->where('attempts', '>=', $maxAttempts)
                ->where(fn (Builder $query): Builder => $query->whereNull('claim_expires_at')->orWhere('claim_expires_at', '<=', $now))
                ->update([
                    'status' => ReminderStatus::Failed,
                    'failed_at' => $now,
                    'failure_reason' => $reason,
                    'claim_token' => null,
                    'claimed_at' => null,
                    'claim_expires_at' => null,
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                return false;
            }

            event(new ReminderFailed(
                $reminder->id,
                $reminder->recipient_type,
                $reminder->recipient_id,
                $reminder->remindable_type,
                $reminder->remindable_id,
                $reminder->owner_type,
                $reminder->owner_id,
                $reminder->reminder_type,
                $reason,
            ));

            return true;
        });
    }

    public function releaseClaim(Reminder $reminder, string $claimToken): bool
    {
        $this->guardOwnerWrite($reminder);

        if ($claimToken === '') {
            return false;
        }

        return Reminder::query()->forOwner()
            ->whereKey($reminder->getKey())
            ->pending()
            ->where('claim_token', $claimToken)
            ->update([
                'claim_token' => null,
                'claimed_at' => null,
                'claim_expires_at' => null,
                'updated_at' => CarbonImmutable::now(),
            ]) === 1;
    }

    private function guardOwnerWrite(Reminder $reminder): void
    {
        if (Reminder::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Reminder::class, $reminder->getKey());
        }
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

    public function recordReminder(CanInteract $recipient, Remindable $subject, string $reminderType, array $options = []): array
    {
        return $this->setReminder($recipient, $subject, $reminderType, $options)->toArray();
    }
}
