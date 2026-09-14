<?php

declare(strict_types=1);

namespace AIArmada\Chip\Commands;

use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Support\PurchaseIdempotencyLedger;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use Illuminate\Console\Command;

final class PruneIdempotencyStubsCommand extends Command
{
    protected $signature = 'chip:prune-idempotency-stubs
                            {--limit=1000 : Maximum number of stubs to delete}
                            {--dry-run : Report expired stubs without deleting}';

    protected $description = 'Delete expired unrecorded purchase idempotency reservations';

    public function handle(PurchaseIdempotencyLedger $ledger): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        if (! (bool) config('chip.owner.enabled', false) || OwnerContext::resolve() !== null) {
            $count = $dryRun
                ? $ledger->countExpiredReservations($limit)
                : $ledger->pruneExpiredReservations($limit);

            $this->report($count, $dryRun);

            return self::SUCCESS;
        }

        $owners = Purchase::query()
            ->withoutOwnerScope()
            ->select(['owner_type', 'owner_id'])
            ->distinct()
            ->orderBy('owner_type')
            ->orderBy('owner_id')
            ->get();

        if ($owners->isEmpty()) {
            $count = OwnerContext::withOwner(null, fn (): int => $dryRun
                ? $ledger->countExpiredReservations($limit)
                : $ledger->pruneExpiredReservations($limit));

            $this->report($count, $dryRun);

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($owners as $row) {
            if ($total >= $limit) {
                break;
            }

            $remaining = $limit - $total;
            $owner = OwnerTupleParser::fromRow($row, OwnerTupleColumns::forModelClass(Purchase::class))->toOwnerModel();

            $total += OwnerContext::withOwner($owner, fn (): int => $dryRun
                ? $ledger->countExpiredReservations($remaining)
                : $ledger->pruneExpiredReservations($remaining));
        }

        $this->report($total, $dryRun);

        return self::SUCCESS;
    }

    private function report(int $count, bool $dryRun): void
    {
        if ($dryRun) {
            $this->info("Found {$count} expired idempotency stub(s).");

            return;
        }

        $this->info("Pruned {$count} expired idempotency stub(s).");
    }
}
