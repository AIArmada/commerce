<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Reminders\Actions;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Engagement\Features\Reminders\Actions\ManageRemindersAction;
use AIArmada\Engagement\Enums\ReminderStatus;
use AIArmada\Engagement\Events\ReminderDue;
use AIArmada\Engagement\Features\Reminders\Models\Reminder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class SendDueRemindersAction
{
    public function __construct(
        private ManageRemindersAction $reminderManager,
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
                    $query = Reminder::query()
                        ->whereIn('status', [ReminderStatus::Pending, ReminderStatus::Scheduled])
                        ->where('remind_at', '<=', $now)
                        ->where(function ($query) use ($now): void {
                            $query->whereNull('expires_at')
                                ->orWhere('expires_at', '>', $now);
                        })
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

                    $reminders = $query->limit(100)->get();

                    foreach ($reminders as $reminder) {
                        $lastRemindAt = $reminder->getRawOriginal('remind_at');
                        $lastId = $reminder->getKey();

                        if (! $this->sendReminder($reminder)) {
                            continue;
                        }

                        $remaining--;
                        $count++;

                        if ($remaining === 0) {
                            break;
                        }
                    }
                } while ($remaining > 0 && $reminders->count() === 100);

                return $count;
            })->sum();
        });

        return $count;
    }

    private function sendReminder(Reminder $reminder): bool
    {
        try {
            return DB::transaction(function () use ($reminder): bool {
                $now = CarbonImmutable::now();
                $lockedReminder = Reminder::query()
                    ->pending()
                    ->whereKey($reminder->getKey())
                    ->where('remind_at', '<=', $now)
                    ->where(function ($query) use ($now): void {
                        $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
                    })
                    ->lockForUpdate()
                    ->first();

                if (! $lockedReminder instanceof Reminder) {
                    return false;
                }

                event(new ReminderDue($lockedReminder));
                $this->reminderManager->markSent($lockedReminder);

                return true;
            });
        } catch (Throwable $exception) {
            try {
                $this->reminderManager->markFailed($reminder, Str::limit($exception->getMessage(), 1000) ?: 'Reminder dispatch failed.');
            } catch (Throwable) {
                // The reminder left the pending window (or its owner scope)
                // while failing; there is nothing left to record.
            }

            return false;
        }
    }
}
