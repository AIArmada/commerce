<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Console\Commands;

use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Services\AffiliatePayoutService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class ProcessScheduledPayoutsCommand extends Command
{
    protected $signature = 'affiliates:process-payouts 
        {--dry-run : Show what would be processed without making changes}
        {--affiliate= : Process payouts for a specific affiliate ID}
        {--min-amount= : Minimum amount threshold in minor units}';

    protected $description = 'Process scheduled payouts for affiliates';

    public function __construct(
        private readonly AffiliatePayoutService $payoutService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $affiliateId = $this->option('affiliate');
        $minAmount = $this->option('min-amount') ?? config('affiliates.payouts.minimum_amount', 5000);

        $this->info('Processing scheduled payouts...');

        if ($dryRun) {
            $this->warn('Running in dry-run mode - no changes will be made.');
        }

        $result = $this->processForOwners($dryRun, $affiliateId, (int) $minAmount);

        $this->outputSummary($result['processed'], $result['skipped'], $result['errors']);

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{processed: int, skipped: int, errors: int}
     */
    private function processForOwners(bool $dryRun, ?string $affiliateId, int $minAmount): array
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return $this->processScoped($dryRun, $affiliateId, $minAmount);
        }

        $owner = OwnerContext::resolve();
        if ($owner !== null) {
            return $this->processScoped($dryRun, $affiliateId, $minAmount);
        }

        $owners = Affiliate::query()
            ->withoutOwnerScope()
            ->select(['owner_type', 'owner_id'])
            ->distinct()
            ->get();

        if ($owners->isEmpty()) {
            return $this->processScoped($dryRun, $affiliateId, $minAmount);
        }

        $totals = [
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        foreach ($owners as $row) {
            $owner = $this->resolveOwnerFromRow($row);

            $result = OwnerContext::withOwner(
                $owner,
                fn (): array => $this->processScoped($dryRun, $affiliateId, $minAmount)
            );

            $totals['processed'] += $result['processed'];
            $totals['skipped'] += $result['skipped'];
            $totals['errors'] += $result['errors'];
        }

        return $totals;
    }

    /**
     * @return array{processed: int, skipped: int, errors: int}
     */
    private function processScoped(bool $dryRun, ?string $affiliateId, int $minAmount): array
    {
        $processed = 0;
        $skipped = 0;
        $errors = 0;

        $query = Affiliate::query()
            ->where('status', 'active')
            ->whereHas('balance', function ($q) use ($minAmount): void {
                $q->where('available_minor', '>=', $minAmount);
            });

        if ($affiliateId) {
            $query->where('id', $affiliateId);
        }

        $affiliates = $query->with('balance')->get();

        $this->output->progressStart($affiliates->count());

        foreach ($affiliates as $affiliate) {
            try {
                $result = $this->processAffiliate($affiliate, $minAmount, $dryRun);

                if ($result === 'processed') {
                    $processed++;
                } else {
                    $skipped++;
                }
            } catch (Exception $e) {
                $errors++;
                $this->error("Error processing affiliate {$affiliate->id}: {$e->getMessage()}");
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function outputSummary(int $processed, int $skipped, int $errors): void
    {
        $this->info("Processed: {$processed}");
        $this->info("Skipped: {$skipped}");

        if ($errors > 0) {
            $this->error("Errors: {$errors}");
        }
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

    private function processAffiliate(Affiliate $affiliate, int $minAmount, bool $dryRun): string
    {
        $balance = $affiliate->balance;

        if (! $balance || $balance->available_minor < $minAmount) {
            return 'skipped';
        }

        // Check for holds
        $hasHold = $affiliate->payoutHolds()
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($hasHold) {
            return 'skipped';
        }

        // Check for pending payouts
        $hasPendingPayout = $affiliate->payouts()
            ->whereIn('status', [PayoutStatus::Pending->value, PayoutStatus::Processing->value])
            ->exists();

        if ($hasPendingPayout) {
            return 'skipped';
        }

        if ($dryRun) {
            $this->line("Would create payout for affiliate {$affiliate->id}: {$balance->available_minor} {$balance->currency}");

            return 'processed';
        }

        // Create payout
        DB::transaction(function () use ($affiliate, $balance): void {
            $payout = AffiliatePayout::create([
                'reference' => 'PAY-' . mb_strtoupper(bin2hex(random_bytes(8))),
                'payee_type' => Affiliate::class,
                'payee_id' => $affiliate->id,
                'owner_type' => $affiliate->owner_type,
                'owner_id' => $affiliate->owner_id,
                'total_minor' => $balance->available_minor,
                'currency' => $balance->currency,
                'status' => PayoutStatus::Pending->value,
                'scheduled_at' => now(),
            ]);

            // Deduct from balance
            $balance->decrement('available_minor', $payout->total_minor);

            // Link approved conversions
            $affiliate->conversions()
                ->where('status', 'approved')
                ->whereNull('affiliate_payout_id')
                ->update(['affiliate_payout_id' => $payout->id]);

            // Create audit event
            $payout->events()->create([
                'to_status' => PayoutStatus::Pending->value,
                'notes' => 'Payout created via scheduled processing',
            ]);
        });

        return 'processed';
    }
}
