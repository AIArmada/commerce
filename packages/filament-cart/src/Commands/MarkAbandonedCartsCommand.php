<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\FilamentCart\Models\Cart;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MarkAbandonedCartsCommand extends Command
{
    protected $signature = 'cart:mark-abandoned
                            {--minutes= : Minutes of inactivity before marking as abandoned}
                            {--dry-run : Show what would be marked without actually updating}
                            {--all-owners : Process every owner when no owner context is available}
                            {--confirm-all-owners : Confirm multi-owner mutation (required when not using --dry-run)}
                            {--max-affected=1000 : Maximum carts that may be marked in one run}
                            {--force-threshold : Allow updates above max-affected threshold}
                            {--strict-owner-tuples : Abort when encountering malformed owner tuples}';

    protected $description = 'Mark carts as abandoned based on inactivity after starting checkout';

    public function handle(): int
    {
        $minutesOption = $this->option('minutes');
        $minutes = is_numeric($minutesOption)
            ? (int) $minutesOption
            : (int) config('filament-cart.monitoring.abandonment_detection_minutes', 30);
        $dryRun = (bool) $this->option('dry-run');
        $allOwners = (bool) $this->option('all-owners');
        $confirmAllOwners = (bool) $this->option('confirm-all-owners');
        $maxAffected = max(1, (int) $this->option('max-affected'));
        $forceThreshold = (bool) $this->option('force-threshold');
        $strictOwnerTuples = (bool) $this->option('strict-owner-tuples');
        $correlationId = (string) Str::uuid();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No carts will be updated');
            $this->newLine();
        }

        if ($allOwners && ! $dryRun && ! $confirmAllOwners) {
            $this->error('Multi-owner mutation requires --confirm-all-owners. Run once with --dry-run first, then rerun with --confirm-all-owners.');

            return self::FAILURE;
        }

        $this->info("Checking for abandoned carts (inactive for {$minutes}+ minutes after starting checkout)...");

        if (Cart::ownerScopingEnabled() && OwnerContext::resolve() === null && ! OwnerContext::isExplicitGlobal()) {
            if (! $allOwners) {
                $this->error('Owner scoping is enabled but no owner context was resolved. Pass --all-owners to process every owner.');

                return self::FAILURE;
            }

            return $this->handleAllOwners(
                minutes: $minutes,
                dryRun: $dryRun,
                maxAffected: $maxAffected,
                forceThreshold: $forceThreshold,
                correlationId: $correlationId,
                strictOwnerTuples: $strictOwnerTuples,
            );
        }

        $candidateCount = $this->countAbandonedCarts($minutes);

        if (! $dryRun && ! $forceThreshold && $candidateCount > $maxAffected) {
            $this->error("Refusing to mark {$candidateCount} carts: exceeds --max-affected={$maxAffected}. Use --force-threshold to proceed.");

            return self::FAILURE;
        }

        $marked = $this->processAbandonedCarts($minutes, $dryRun);

        logger()->info('cart.mark_abandoned.executed', [
            'correlation_id' => $correlationId,
            'scope_mode' => OwnerContext::isExplicitGlobal() ? 'global' : 'owner',
            'dry_run' => $dryRun,
            'all_owners' => false,
            'confirm_all_owners' => $confirmAllOwners,
            'max_affected' => $maxAffected,
            'force_threshold' => $forceThreshold,
            'total_candidates' => $candidateCount,
            'total_marked' => $marked,
            'owner_breakdown' => [],
        ]);

        $this->newLine();
        $this->info("Marked {$marked} cart(s) as abandoned.");

        return self::SUCCESS;
    }

    private function handleAllOwners(
        int $minutes,
        bool $dryRun,
        int $maxAffected,
        bool $forceThreshold,
        string $correlationId,
        bool $strictOwnerTuples,
    ): int
    {
        $columns = OwnerTupleColumns::forModelClass(Cart::class);

        $owners = Cart::query()
            ->withoutOwnerScope()
            ->select([
                $columns->ownerTypeColumn . ' as owner_type',
                $columns->ownerIdColumn . ' as owner_id',
            ])
            ->distinct()
            ->get();

        $ownerBatches = [];
        $totalCandidates = 0;

        foreach ($owners as $row) {
            $parsed = OwnerTupleParser::fromRow(
                row: $row,
                columns: new OwnerTupleColumns,
                allowMalformed: true,
            );

            if ($parsed->isUnresolved()) {
                if ($strictOwnerTuples) {
                    $this->error(sprintf(
                        'Malformed owner tuple encountered (owner_type: %s, owner_id: %s).',
                        is_string($row->owner_type ?? null) && $row->owner_type !== '' ? $row->owner_type : 'null',
                        is_string($row->owner_id ?? null) || is_int($row->owner_id ?? null) ? (string) $row->owner_id : 'null',
                    ));

                    return self::FAILURE;
                }

                $this->warn(sprintf(
                    'Skipping malformed owner tuple while marking abandoned carts (owner_type: %s, owner_id: %s).',
                    is_string($row->owner_type ?? null) && $row->owner_type !== '' ? $row->owner_type : 'null',
                    is_string($row->owner_id ?? null) || is_int($row->owner_id ?? null) ? (string) $row->owner_id : 'null',
                ));

                continue;
            }

            $owner = $parsed->toOwnerModel();
            $count = (int) OwnerContext::withOwner($owner, fn (): int => $this->countAbandonedCarts($minutes));

            $ownerBatches[] = [
                'owner_type' => $parsed->owner_type,
                'owner_id' => $parsed->owner_id !== null ? (string) $parsed->owner_id : null,
                'owner' => $owner,
                'candidate_count' => $count,
            ];
            $totalCandidates += $count;
        }

        if ($owners->isEmpty()) {
            $count = (int) OwnerContext::withOwner(null, fn (): int => $this->countAbandonedCarts($minutes));
            $ownerBatches[] = [
                'owner_type' => null,
                'owner_id' => null,
                'owner' => null,
                'candidate_count' => $count,
            ];
            $totalCandidates += $count;
        }

        if (! $dryRun && ! $forceThreshold && $totalCandidates > $maxAffected) {
            $this->error("Refusing to mark {$totalCandidates} carts: exceeds --max-affected={$maxAffected}. Use --force-threshold to proceed.");

            return self::FAILURE;
        }

        $totalMarked = 0;
        $ownerBreakdown = [];

        foreach ($ownerBatches as $batch) {
            $marked = (int) OwnerContext::withOwner(
                $batch['owner'],
                fn (): int => $this->processAbandonedCarts($minutes, $dryRun)
            );

            $totalMarked += $marked;
            $ownerBreakdown[] = [
                'owner_type' => $batch['owner_type'],
                'owner_id' => $batch['owner_id'],
                'candidate_count' => $batch['candidate_count'],
                'marked_count' => $marked,
            ];
        }

        logger()->info('cart.mark_abandoned.executed', [
            'correlation_id' => $correlationId,
            'scope_mode' => 'all-owners',
            'dry_run' => $dryRun,
            'all_owners' => true,
            'confirm_all_owners' => ! $dryRun,
            'max_affected' => $maxAffected,
            'force_threshold' => $forceThreshold,
            'total_candidates' => $totalCandidates,
            'total_marked' => $totalMarked,
            'owner_breakdown' => $ownerBreakdown,
        ]);

        $this->newLine();
        $this->info("Total marked: {$totalMarked} cart(s) as abandoned.");

        return self::SUCCESS;
    }

    private function processAbandonedCarts(int $minutes, bool $dryRun): int
    {
        $carts = $this->abandonedCartsQuery($minutes)->get();

        if ($carts->isEmpty()) {
            $this->info('  No carts need to be marked as abandoned.');

            return 0;
        }

        $marked = 0;

        foreach ($carts as $cart) {
            $this->line(sprintf(
                '  Cart %s: %d items, %s - checkout started %s',
                mb_substr((string) $cart->id, 0, 8) . '...',
                $cart->items_count,
                $cart->formatted_total,
                $cart->checkout_started_at?->diffForHumans() ?? 'unknown'
            ));

            if (! $dryRun) {
                $cart->markAsAbandoned();
            }

            $marked++;
        }

        return $marked;
    }

    private function countAbandonedCarts(int $minutes): int
    {
        return $this->abandonedCartsQuery($minutes)->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Cart>
     */
    private function abandonedCartsQuery(int $minutes): \Illuminate\Database\Eloquent\Builder
    {
        $cutoff = now()->subMinutes($minutes);

        return Cart::query()->forOwner()
            ->where('items_count', '>', 0)
            ->whereNotNull('checkout_started_at')
            ->whereNull('checkout_abandoned_at')
            ->where(function ($q) use ($cutoff): void {
                $q->where('last_activity_at', '<', $cutoff)
                    ->orWhere(function ($sub) use ($cutoff): void {
                        $sub->whereNull('last_activity_at')
                            ->where('checkout_started_at', '<', $cutoff);
                    });
            });
    }

}
