<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Console\Commands;

use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Events\ReminderDue;
use AIArmada\Engagement\Models\Reminder;
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
        $count = 0;

        foreach ($this->reminderManager->dueReminders() as $reminder) {
            if ($reminder->status !== Reminder::STATUS_PENDING && $reminder->status !== Reminder::STATUS_SCHEDULED) {
                continue;
            }

            event(new ReminderDue($reminder));
            $this->reminderManager->markSent($reminder);
            $count++;
        }

        $this->info("Sent {$count} reminders.");

        return self::SUCCESS;
    }
}
