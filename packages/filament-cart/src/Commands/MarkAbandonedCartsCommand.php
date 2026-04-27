<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Models\Cart;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class MarkAbandonedCartsCommand extends Command
{
    protected $signature = 'cart:mark-abandoned
                            {--minutes=30 : Minutes of inactivity before marking as abandoned}
                            {--dry-run : Show what would be marked without actually updating}';

    protected $description = 'Mark carts as abandoned based on inactivity after starting checkout';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No carts will be updated');
            $this->newLine();
        }

        $this->info("Checking for abandoned carts (inactive for {$minutes}+ minutes after starting checkout)...");

        if (Cart::ownerScopingEnabled() && OwnerContext::resolve() === null) {
            return $this->handleAllOwners($minutes, $dryRun);
        }

        $marked = $this->processAbandonedCarts($minutes, $dryRun);

        $this->newLine();
        $this->info("Marked {$marked} cart(s) as abandoned.");

        return self::SUCCESS;
    }

    private function handleAllOwners(int $minutes, bool $dryRun): int
    {
        $owners = Cart::query()
            ->withoutOwnerScope()
            ->select(['owner_type', 'owner_id'])
            ->distinct()
            ->get();

        if ($owners->isEmpty()) {
            $marked = $this->processAbandonedCarts($minutes, $dryRun);

            $this->newLine();
            $this->info("Marked {$marked} cart(s) as abandoned.");

            return self::SUCCESS;
        }

        $totalMarked = 0;

        foreach ($owners as $row) {
            $owner = $this->resolveOwnerFromRow($row);

            $totalMarked += (int) OwnerContext::withOwner(
                $owner,
                fn (): int => $this->processAbandonedCarts($minutes, $dryRun)
            );
        }

        $this->newLine();
        $this->info("Total marked: {$totalMarked} cart(s) as abandoned.");

        return self::SUCCESS;
    }

    private function processAbandonedCarts(int $minutes, bool $dryRun): int
    {
        $cutoff = now()->subMinutes($minutes);

        $query = Cart::query()->forOwner()
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

        $carts = $query->get();

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
