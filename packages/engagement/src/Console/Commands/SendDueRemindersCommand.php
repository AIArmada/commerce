<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Engagement\Contracts\ReminderManager;
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
        $count = 0;
        $now = CarbonImmutable::now();
        $batchSize = (int) config('engagement.reminder.batch_size', 100);

        $reminders = Reminder::query()
            ->withoutOwnerScope()
            ->whereIn('status', [Reminder::STATUS_PENDING, Reminder::STATUS_SCHEDULED])
            ->where('remind_at', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            })
            ->orderBy('remind_at')
            ->limit($batchSize)
            ->cursor();

        foreach ($reminders as $reminder) {
            if ($reminder->status !== Reminder::STATUS_PENDING && $reminder->status !== Reminder::STATUS_SCHEDULED) {
                continue;
            }

            $owner = OwnerTupleParser::fromTypeAndId(
                $reminder->owner_type,
                $reminder->owner_id,
            )->toOwnerModel();

            OwnerContext::withOwner($owner, function () use ($reminder): void {
                event(new ReminderDue($reminder));
                $this->reminderManager->markSent($reminder);
            });

            $count++;
        }

        $this->info("Sent {$count} reminders.");

        return self::SUCCESS;
    }
}
