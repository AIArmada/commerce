<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Reminders\Actions;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Engagement\Events\ReminderDue;
use AIArmada\Engagement\Features\Reminders\Models\Reminder;
use AIArmada\Engagement\Features\Reminders\Queries\ReadDueRemindersQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class SendDueRemindersAction
{
    public function __construct(
        private ManageRemindersAction $reminderManager,
        private ReadDueRemindersQuery $dueReminders,
        private Dispatcher $events,
    ) {}

    public function execute(): int
    {
        $now = CarbonImmutable::now();
        $remaining = max(0, (int) config('engagement.reminder.batch_size', 100));
        $runner = new OwnerBatchRunner(
            Reminder::class,
            [
                'enabled' => 'engagement.owner.enabled',
                'include_global' => 'engagement.owner.include_global',
            ],
        );

        $count = OwnerContext::withOwner(null, function () use ($runner, $now, &$remaining): int {
            return (int) $runner->forEach(function () use ($now, &$remaining): int {
                if ($remaining === 0) {
                    return 0;
                }

                $count = 0;
                $lastRemindAt = null;
                $lastId = null;

                do {
                    $query = $this->dueReminders->query($now)
                        ->orderBy('remind_at')
                        ->orderBy('id');

                    if ($lastId !== null) {
                        $query->where(function ($query) use ($lastRemindAt, $lastId): void {
                            $query->where('remind_at', '>', $lastRemindAt)
                                ->orWhere(function ($query) use ($lastRemindAt, $lastId): void {
                                    $query->where('remind_at', $lastRemindAt)->where('id', '>', $lastId);
                                });
                        });
                    }

                    $pageSize = min(100, $remaining);
                    $reminders = $query->limit($pageSize)->get();

                    foreach ($reminders as $reminder) {
                        $lastRemindAt = $reminder->getRawOriginal('remind_at');
                        $lastId = $reminder->getKey();

                        $remaining--;
                        if ($this->sendReminder($reminder)) {
                            $count++;
                        }

                        if ($remaining === 0) {
                            break;
                        }
                    }
                } while ($remaining > 0 && $reminders->count() === $pageSize);

                return $count;
            })->sum();
        });

        return $count;
    }

    private function sendReminder(Reminder $reminder): bool
    {
        try {
            return $reminder->getConnection()->transaction(function () use ($reminder): bool {
                $now = CarbonImmutable::now();
                $lockedReminder = $this->dueReminders->query($now)
                    ->whereKey($reminder->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $lockedReminder instanceof Reminder) {
                    return false;
                }

                if (! $this->events->hasListeners(ReminderDue::class)) {
                    throw new RuntimeException('No reminder delivery listener is registered.');
                }

                $this->events->dispatch(new ReminderDue(
                    $lockedReminder->id,
                    $lockedReminder->recipient_type,
                    $lockedReminder->recipient_id,
                    $lockedReminder->remindable_type,
                    $lockedReminder->remindable_id,
                    $lockedReminder->owner_type,
                    $lockedReminder->owner_id,
                    $lockedReminder->reminder_type,
                ));
                $this->reminderManager->markSent($lockedReminder);

                return true;
            });
        } catch (Throwable $exception) {
            try {
                $this->reminderManager->markFailed($reminder, Str::limit($exception->getMessage(), 1000) ?: 'Reminder dispatch failed.');
            } catch (Throwable $failure) {
                report($failure);
            }

            report($exception);

            return false;
        }
    }
}
