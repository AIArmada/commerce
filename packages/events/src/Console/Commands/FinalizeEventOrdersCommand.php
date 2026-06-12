<?php

declare(strict_types=1);

namespace AIArmada\Events\Console\Commands;

use AIArmada\Events\Actions\FinalizeOccurredEventOrdersAction;
use AIArmada\Events\Models\EventOccurrence;
use Illuminate\Console\Command;

final class FinalizeEventOrdersCommand extends Command
{
    protected $signature = 'events:finalize-orders
        {--occurrence= : The occurrence ID to finalize}
        {--dry-run : Perform a dry run without making changes}';

    protected $description = 'Finalize orders for completed event occurrences';

    public function handle(FinalizeOccurredEventOrdersAction $action): int
    {
        $occurrenceId = $this->option('occurrence');
        $dryRun = (bool) $this->option('dry-run');

        $query = EventOccurrence::query()->where('status', EventOccurrence::COMPLETED);

        if ($occurrenceId !== null) {
            $query->whereKey($occurrenceId);
        }

        $occurrences = $query->get();

        if ($occurrences->isEmpty()) {
            $this->info('No completed occurrences found.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d completed occurrence(s).', $occurrences->count()));

        foreach ($occurrences as $occurrence) {
            if ($dryRun) {
                $this->line(sprintf('[DRY RUN] Would finalize orders for occurrence %s', $occurrence->getKey()));

                continue;
            }

            $action->handle($occurrence);

            $this->line(sprintf('Finalized orders for occurrence %s', $occurrence->getKey()));
        }

        return self::SUCCESS;
    }
}
