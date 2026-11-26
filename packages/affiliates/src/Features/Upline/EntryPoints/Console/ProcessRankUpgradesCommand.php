<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\EntryPoints\Console;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateReportingReader;
use AIArmada\Affiliates\Features\Upline\Api\RankQualificationApi;
use Illuminate\Console\Command;

final class ProcessRankUpgradesCommand extends Command
{
    protected $signature = 'affiliates:process-ranks
                            {--dry-run : Show what would be changed without making changes}';

    protected $description = 'Process rank qualifications and upgrades for all affiliates';

    public function handle(RankQualificationApi $service): int
    {
        if ($this->option('dry-run')) {
            $this->info('Dry run mode - no changes will be made.');
        }

        $this->info('Processing rank qualifications...');

        $upgraded = app(AffiliateReportingReader::class)->runForOwners(fn (): int => $service->processAllRankUpgrades((bool) $this->option('dry-run')));

        $this->info($this->option('dry-run') ? "Would change ranks for {$upgraded} affiliates." : "Processed rank changes for {$upgraded} affiliates.");

        return self::SUCCESS;
    }
}
