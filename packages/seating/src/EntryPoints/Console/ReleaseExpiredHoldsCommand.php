<?php

declare(strict_types=1);

namespace AIArmada\Seating\EntryPoints\Console;

use AIArmada\Seating\Actions\ReleaseExpiredHoldsAction;
use Illuminate\Console\Command;

final class ReleaseExpiredHoldsCommand extends Command
{
    protected $signature = 'seating:release-expired-holds {--chunk=500}';

    protected $description = 'Release seat holds whose expires_at is in the past.';

    public function handle(ReleaseExpiredHoldsAction $action): int
    {
        $released = $action->handle((int) $this->option('chunk'));

        $this->info("Released {$released} expired hold(s).");

        return self::SUCCESS;
    }
}
