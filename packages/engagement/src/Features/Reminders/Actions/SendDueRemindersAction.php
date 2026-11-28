<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Reminders\Actions;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Engagement\Events\ReminderDue;
use AIArmada\Engagement\Features\Reminders\Models\Reminder;
use AIArmada\Engagement\Features\Reminders\Queries\ReadDueRemindersQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
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
        $claimSeconds = max(1, (int) config('engagement.reminder.claim_ttl', 300));
        $maxAttempts = max(1, (int) config('engagement.reminder.max_attempts', 3));
        $runner = new OwnerBatchRunner(
            Reminder::class,
            [
                'enabled' => 'engagement.owner.enabled',
                'include_global' => 'engagement.owner.include_global',
            ],
        );

        $count = (int) $runner->forEachAcrossOwners(function () use ($now, $claimSeconds, $maxAttempts, &$remaining): int {
            if ($remaining === 0) {
                return 0;
            }

            $count = 0;
            $lastRemindAt = null;
            $lastId = null;

            do {
                $query = $this->dueReminders->query($now)
                    ->where(fn (Builder $query): Builder => $query->whereNull('claim_expires_at')->orWhere('claim_expires_at', '<=', $now))
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

                    $result = $this->sendReminder($reminder, $claimSeconds, $maxAttempts);

                    if ($result === null) {
                        continue;
                    }

                    $remaining--;

                    if ($result) {
                        $count++;
                    }

                    if ($remaining === 0) {
                        break;
                    }
                }
            } while ($remaining > 0 && $reminders->count() === $pageSize);

            return $count;
        })->sum();

        return $count;
    }

    private function sendReminder(Reminder $reminder, int $claimSeconds, int $maxAttempts): ?bool
    {
        $attemptNow = CarbonImmutable::now();
        $claimToken = (string) Str::uuid();

        if (! $this->reminderManager->claimForDelivery($reminder, $attemptNow, $claimToken, $attemptNow->addSeconds($claimSeconds), $maxAttempts)) {
            return $this->reminderManager->failExhaustedClaim($reminder, $attemptNow, $maxAttempts) ? false : null;
        }

        try {
            $claimed = $this->dueReminders->query($attemptNow)
                ->whereKey($reminder->getKey())
                ->where('claim_token', $claimToken)
                ->first();

            if (! $claimed instanceof Reminder) {
                $this->reminderManager->releaseClaim($reminder, $claimToken);

                return null;
            }

            if (! $this->events->hasListeners(ReminderDue::class)) {
                throw new RuntimeException('No reminder delivery listener is registered.');
            }

            $this->events->dispatch(new ReminderDue(
                $claimed->id,
                $claimed->recipient_type,
                $claimed->recipient_id,
                $claimed->remindable_type,
                $claimed->remindable_id,
                $claimed->owner_type,
                $claimed->owner_id,
                $claimed->reminder_type,
                $claimToken,
            ));

            return $this->reminderManager->completeClaim($claimed, $claimToken);
        } catch (Throwable $exception) {
            try {
                $this->reminderManager->failClaim($reminder, $claimToken, Str::limit($exception->getMessage(), 1000) ?: 'Reminder dispatch failed.');
            } catch (Throwable $failure) {
                report($failure);
            }

            report($exception);

            return false;
        }
    }
}
