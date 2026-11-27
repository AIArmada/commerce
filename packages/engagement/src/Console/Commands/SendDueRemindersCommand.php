<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Console\Commands;

use AIArmada\Engagement\Actions\SendDueRemindersAction;
use Illuminate\Console\Command;

final class SendDueRemindersCommand extends Command
{
    protected $signature = 'engagement:send-due-reminders';

    protected $description = 'Send due reminders';

    public function handle(SendDueRemindersAction $action): int
    {
        $count = $action->execute();

        $this->info("Sent {$count} reminders.");

        return self::SUCCESS;
    }
}
