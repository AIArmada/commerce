<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Subscriptions\EntryPoints\Commands;

use AIArmada\Engagement\Features\Subscriptions\Actions\MatchSubscriptionsAction;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class MatchSubscriptionsCommand extends Command
{
    protected $signature = 'engagement:match-subscriptions
                            {subjectType : The morph class type of the subject}
                            {subjectId : The ID of the subject}
                            {--trigger= : The trigger event name}';

    protected $description = 'Match subscriptions to a subject';

    public function handle(MatchSubscriptionsAction $action): int
    {
        $trigger = $this->option('trigger');

        if (! is_string($trigger) || $trigger === '') {
            $this->error('The --trigger option is required.');

            return self::FAILURE;
        }

        try {
            $processed = $action->execute((string) $this->argument('subjectType'), (string) $this->argument('subjectId'), $trigger);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Processed {$processed} subscription matches for trigger: {$trigger}");

        return self::SUCCESS;
    }
}
