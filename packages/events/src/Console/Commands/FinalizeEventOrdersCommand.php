<?php

declare(strict_types=1);

namespace AIArmada\Events\Console\Commands;

use AIArmada\Events\Actions\FinalizeOccurredEventOrdersAction;
use Illuminate\Console\Command;

final class FinalizeEventOrdersCommand extends Command
{
    protected $signature = 'events:finalize-orders';

    protected $description = 'Mark ended event registrations as no-show and complete eligible event orders';

    public function handle(FinalizeOccurredEventOrdersAction $finalize): int
    {
        $result = $finalize->handle();

        $this->info('Finished finalizing ended event orders.');
        $this->line(sprintf('Orders reviewed: %d', $result['orders_reviewed']));
        $this->line(sprintf('Registrations marked no-show: %d', $result['registrations_marked_no_show']));
        $this->line(sprintf('Orders completed: %d', $result['orders_completed']));

        return self::SUCCESS;
    }
}
