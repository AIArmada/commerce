<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\CommissionMaturityService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
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

        $matured = $this->processForOwners(fn (): int => $this->maturityService->processMaturity());

        $this->info("Matured {$matured} conversions.");

        return self::SUCCESS;
    }

    private function processForOwners(callable $callback): int
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return (int) $callback();
        }

        $owner = OwnerContext::resolve();
        if ($owner !== null) {
            return (int) $callback();
        }

        $columns = OwnerTupleColumns::forModelClass(Affiliate::class);

        $owners = Affiliate::query()
            ->withoutOwnerScope()
            ->select([$columns->ownerTypeColumn, $columns->ownerIdColumn])
            ->distinct()
            ->get();

        if ($owners->isEmpty()) {
            return (int) OwnerContext::withOwner(null, $callback);
        }

        $includeGlobal = (bool) config('affiliates.owner.include_global', false);
        if ($includeGlobal) {
            config()->set('affiliates.owner.include_global', false);
        }

        $total = 0;
        $processedGlobal = false;

        try {
            foreach ($owners as $row) {
                $parsed = OwnerTupleParser::fromRow($row, $columns);

                if ($parsed->isExplicitGlobal()) {
                    if ($processedGlobal) {
                        continue;
                    }

                    $processedGlobal = true;
                }

                $total += (int) OwnerContext::withOwner($parsed->toOwnerModel(), $callback);
            }
        } finally {
            if ($includeGlobal) {
                config()->set('affiliates.owner.include_global', true);
            }
        }

        return $total;
    }
}
