<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\EntryPoints\Console;

use AIArmada\Affiliates\Features\Programs\Actions\ProcessCreativeAssetCleanup;
use Illuminate\Console\Command;

final class RetryCreativeAssetCleanupCommand extends Command
{
    protected $signature = 'affiliates:retry-creative-cleanup {--limit=100 : Maximum cleanup records to process}';

    protected $description = 'Retry filesystem cleanup for detached affiliate creative media';

    public function handle(ProcessCreativeAssetCleanup $cleanup): int
    {
        $failures = $cleanup->drain(max(1, min(1000, (int) $this->option('limit'))));

        if ($failures > 0) {
            $this->error("Creative cleanup failed for {$failures} records; they remain queued.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
