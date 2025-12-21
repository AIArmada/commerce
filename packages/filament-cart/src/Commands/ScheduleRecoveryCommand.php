<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Models\RecoveryCampaign;
use AIArmada\FilamentCart\Services\RecoveryScheduler;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class ScheduleRecoveryCommand extends Command
{
    protected $signature = 'cart:schedule-recovery
                            {--campaign= : Specific campaign ID to process}
                            {--dry-run : Show what would be scheduled without actually scheduling}';

    protected $description = 'Schedule recovery attempts for eligible abandoned carts';

    public function handle(RecoveryScheduler $scheduler): int
    {
        $campaignId = $this->option('campaign');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Running in dry-run mode. No changes will be made.');
        }

        if (RecoveryCampaign::ownerScopingEnabled() && OwnerContext::resolve() === null) {
            return $this->handleAllOwners($scheduler, $campaignId, (bool) $dryRun);
        }

        $totalScheduled = $this->processCampaigns($scheduler, $campaignId, (bool) $dryRun);

        if (! $dryRun) {
            $this->info("Total scheduled: {$totalScheduled} recovery attempts");
        }

        return self::SUCCESS;
    }

    private function handleAllOwners(RecoveryScheduler $scheduler, ?string $campaignId, bool $dryRun): int
    {
        $owners = RecoveryCampaign::query()
            ->withoutOwnerScope()
            ->select(['owner_type', 'owner_id'])
            ->distinct()
            ->get();

        if ($owners->isEmpty()) {
            $totalScheduled = $this->processCampaigns($scheduler, $campaignId, $dryRun);

            if (! $dryRun) {
                $this->info("Total scheduled: {$totalScheduled} recovery attempts");
            }

            return self::SUCCESS;
        }

        $totalScheduled = 0;

        foreach ($owners as $row) {
            $owner = $this->resolveOwnerFromRow($row);

            $totalScheduled += (int) OwnerContext::withOwner(
                $owner,
                fn (): int => $this->processCampaigns($scheduler, $campaignId, $dryRun)
            );
        }

        if (! $dryRun) {
            $this->info("Total scheduled: {$totalScheduled} recovery attempts");
        }

        return self::SUCCESS;
    }

    private function processCampaigns(RecoveryScheduler $scheduler, ?string $campaignId, bool $dryRun): int
    {
        $query = RecoveryCampaign::query()->forOwner()
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });

        if ($campaignId) {
            $query->where('id', $campaignId);
        }

        $campaigns = $query->get();

        if ($campaigns->isEmpty()) {
            $this->info('No active campaigns found.');

            return 0;
        }

        $this->info("Processing {$campaigns->count()} campaign(s)...");
        $totalScheduled = 0;

        foreach ($campaigns as $campaign) {
            $this->line("  Campaign: {$campaign->name}");

            if ($dryRun) {
                $this->line('    [DRY-RUN] Would process campaign');

                continue;
            }

            $scheduled = $scheduler->scheduleForCampaign($campaign);
            $totalScheduled += $scheduled;

            $this->line("    Scheduled: {$scheduled} attempts");
        }

        $this->newLine();

        return $totalScheduled;
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
