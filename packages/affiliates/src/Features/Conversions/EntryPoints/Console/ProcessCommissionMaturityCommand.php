<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\EntryPoints\Console;

use AIArmada\Affiliates\Features\Conversions\Api\ConversionMaturity;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use Illuminate\Console\Command;

final class ProcessCommissionMaturityCommand extends Command
{
    protected $signature = 'affiliates:process-maturity 
        {--dry-run : Show what would be processed without making changes}';

    protected $description = 'Process commission maturity and move to available balance';

    public function __construct(
        private readonly ConversionMaturity $processConversionMaturity,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Processing commission maturity...');

        if ($dryRun) {
            $this->warn('Running in dry-run mode - no changes will be made.');
        }

        $runner = new OwnerBatchRunner(
            Affiliate::class,
            ['enabled' => 'affiliates.owner.enabled', 'include_global' => 'affiliates.owner.include_global'],
        );

        $matured = $runner->run(fn (): int => $this->processConversionMaturity->process($dryRun)) ?? 0;

        $this->info($dryRun ? "Would mature {$matured} conversions." : "Matured {$matured} conversions.");

        return self::SUCCESS;
    }
}
