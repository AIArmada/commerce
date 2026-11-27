<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\EntryPoints\Commands;

use AIArmada\Chip\Features\Collect\Models\Purchase;
use AIArmada\Chip\Features\Collect\Persistence\PurchaseIdempotencyLedger;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use Illuminate\Console\Command;

final class PruneIdempotencyStubsCommand extends Command
{
    protected $signature = 'chip:prune-idempotency-stubs
                            {--limit=1000 : Maximum number of stubs to delete}
                            {--dry-run : Report expired stubs without deleting}
                            {--reservation-id= : Release only this reconciled local reservation}
                            {--confirmed-not-created : Confirm the remote purchase was not created}';

    protected $description = 'Inspect expired purchase reservations or release a reconciled reservation';

    public function handle(PurchaseIdempotencyLedger $ledger): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun) {
            $reservationId = $this->option('reservation-id');

            if (! is_string($reservationId) || $reservationId === '' || ! $this->option('confirmed-not-created')) {
                $this->error('Release requires --reservation-id and --confirmed-not-created after remote reconciliation.');

                return self::FAILURE;
            }

            if ((bool) config('chip.owner.enabled', false) && OwnerContext::resolve() === null && ! OwnerContext::isExplicitGlobal()) {
                $this->error('Release requires an explicit owner context.');

                return self::FAILURE;
            }

            if (! $ledger->releaseReconciledReservation($reservationId)) {
                $this->error('No expired unrecorded reservation was found for this owner.');

                return self::FAILURE;
            }

            $this->info('Released reconciled reservation.');

            return self::SUCCESS;
        }

        if (! (bool) config('chip.owner.enabled', false) || OwnerContext::resolve() !== null) {
            $count = $ledger->countExpiredReservations($limit);

            $this->report($count);

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
            $count = OwnerContext::withOwner(null, fn (): int => $ledger->countExpiredReservations($limit));

            $this->report($count);

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($owners as $row) {
            if ($total >= $limit) {
                break;
            }

            $remaining = $limit - $total;
            $owner = OwnerTupleParser::fromRow($row, OwnerTupleColumns::forModelClass(Purchase::class))->toOwnerModel();

            $total += OwnerContext::withOwner($owner, fn (): int => $ledger->countExpiredReservations($remaining));
        }

        $this->report($total);

        return self::SUCCESS;
    }

    private function report(int $count): void
    {
        $this->info("Found {$count} expired idempotency stub(s).");
    }
}
