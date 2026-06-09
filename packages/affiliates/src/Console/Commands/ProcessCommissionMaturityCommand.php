<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\CommissionMaturityService;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use Illuminate\Console\Command;

final class ProcessCommissionMaturityCommand extends Command
{
    protected $signature = 'affiliates:process-maturity 
        {--dry-run : Show what would be processed without making changes}';

    protected $description = 'Process commission maturity and move to available balance';

    public function __construct(
        private readonly CommissionMaturityService $maturityService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Processing commission maturity...');

        if ($dryRun) {
            $this->warn('Running in dry-run mode - no changes will be made.');

            return self::SUCCESS;
        }

        $runner = new OwnerBatchRunner(
            Affiliate::class,
            ['enabled' => 'affiliates.owner.enabled', 'include_global' => 'affiliates.owner.include_global'],
        );

        $matured = $runner->run(fn (): int => $this->maturityService->processMaturity()) ?? 0;

        $this->info("Matured {$matured} conversions.");

        return self::SUCCESS;
    }
}
