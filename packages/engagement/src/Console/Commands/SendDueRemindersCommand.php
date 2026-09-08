<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Enums\ReminderStatus;
use AIArmada\Engagement\Events\ReminderDue;
use AIArmada\Engagement\Models\Reminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class SendDueRemindersCommand extends Command
{
    protected $signature = 'engagement:send-due-reminders';

    protected $description = 'Send due reminders';

    public function __construct(
        private readonly ReminderManager $reminderManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
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
                Reminder::query()
                    ->whereIn('status', [ReminderStatus::Pending, ReminderStatus::Scheduled])
                    ->where('remind_at', '<=', $now)
                    ->where(function ($query) use ($now): void {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', $now);
                    })
                    ->orderBy('remind_at')
                    ->limit($remaining)
                    ->chunkById(100, function ($reminders) use (&$remaining, &$count): bool {
                        foreach ($reminders as $reminder) {
                            if ($remaining === 0) {
                                return false;
                            }

                            if ($reminder->status !== ReminderStatus::Pending && $reminder->status !== ReminderStatus::Scheduled) {
                                continue;
                            }

                            event(new ReminderDue($reminder));
                            $this->reminderManager->markSent($reminder);
                            $remaining--;
                            $count++;
                        }

                        return $remaining > 0;
                    });

                return $count;
            })->sum();
        });

        $this->info("Sent {$count} reminders.");

        return self::SUCCESS;
    }
}
