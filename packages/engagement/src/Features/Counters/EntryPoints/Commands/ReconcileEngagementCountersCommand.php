<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Counters\EntryPoints\Commands;

use AIArmada\Engagement\Features\Counters\Actions\ReconcileEngagementCountersAction;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class ReconcileEngagementCountersCommand extends Command
{
    protected $signature = 'engagement:reconcile-counters
                            {subjectType? : The morph class type of the subject}
                            {subjectId? : The ID of the subject}
                            {--type= : Only reconcile specific counter types (bookmarks,responses,followers,reactions)}';

    protected $description = 'Reconcile engagement counters from source records';

    public function handle(ReconcileEngagementCountersAction $action): int
    {
        try {
            $action->execute($this->argument('subjectType'), $this->argument('subjectId'), $this->option('type'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Reconciled engagement counters.');

        return self::SUCCESS;
    }
}
