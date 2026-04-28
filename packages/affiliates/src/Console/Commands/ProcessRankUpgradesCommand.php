<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\RankQualificationService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
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

        $upgraded = $this->processForOwners(fn (): int => $service->processAllRankUpgrades());

        $this->info("Processed rank changes for {$upgraded} affiliates.");

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
