<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Services\RankQualificationService;
use Illuminate\Console\Command;

final class ProcessRankUpgradesCommand extends Command
{
    protected $signature = 'affiliates:process-ranks
                            {--dry-run : Show what would be changed without making changes}';

    protected $description = 'Process rank qualifications and upgrades for all affiliates';

    public function handle(RankQualificationService $service): int
    {
        if ($this->option('dry-run')) {
            $this->info('Dry run mode - no changes will be made.');
        }

        $this->info('Processing rank qualifications...');

        $upgraded = $service->processAllRankUpgrades();

        $this->info("Processed rank changes for {$upgraded} affiliates.");

        return self::SUCCESS;
    }
}
