<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\RankQualificationService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

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

        $owners = Affiliate::query()
            ->withoutOwnerScope()
            ->select(['owner_type', 'owner_id'])
            ->distinct()
            ->get();

        if ($owners->isEmpty()) {
            return (int) $callback();
        }

        $total = 0;

        foreach ($owners as $row) {
            $owner = $this->resolveOwnerFromRow($row);
            $total += (int) OwnerContext::withOwner($owner, $callback);
        }

        return $total;
    }

    private function resolveOwnerFromRow(object $row): ?Model
    {
        $ownerType = $row->owner_type ?? null;
        $ownerId = $row->owner_id ?? null;

        return OwnerContext::fromTypeAndId(
            is_string($ownerType) ? $ownerType : null,
            is_string($ownerId) || is_int($ownerId) ? $ownerId : null
        );
    }
}
