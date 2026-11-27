<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Actions;

use AIArmada\Cart\Features\Snapshots\Api\AbandonmentSweep;
use AIArmada\Cart\Features\Snapshots\Api\CartAbandonmentMarker;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final readonly class SweepAbandonedSnapshots implements AbandonmentSweep
{
    public function __construct(private CartAbandonmentMarker $marker) {}

    public function owners(): array
    {
        if (! CartSnapshot::ownerScopingEnabled()) {
            return [];
        }

        if (! OwnerContext::isExplicitGlobal()) {
            throw new AuthorizationException('Cart snapshot owner enumeration requires explicit global owner context.');
        }

        $columns = OwnerTupleColumns::forModelClass(CartSnapshot::class);

        return OwnerContext::withOwner(null, fn (): array => CartSnapshot::query()
            ->withoutOwnerScope()
            ->select([$columns->ownerTypeColumn, $columns->ownerIdColumn])
            ->distinct()
            ->get()
            ->map(fn (CartSnapshot $row): array => [
                'owner_type' => $row->getAttribute($columns->ownerTypeColumn),
                'owner_id' => $row->getAttribute($columns->ownerIdColumn),
            ])->values()->all());
    }

    public function count(CarbonImmutable $cutoff): int
    {
        return $this->query($cutoff)->count();
    }

    public function execute(CarbonImmutable $cutoff): int
    {
        $marked = 0;

        $this->query($cutoff)->chunkById(500, function ($snapshots) use (&$marked, $cutoff): void {
            foreach ($snapshots as $snapshot) {
                if ($this->marker->execute((string) $snapshot->getKey(), $cutoff)) {
                    $marked++;
                }
            }
        });

        return $marked;
    }

    /** @return Builder<CartSnapshot> */
    private function query(CarbonImmutable $cutoff): Builder
    {
        return CartSnapshot::query()->forOwner(includeGlobal: false)
            ->where('items_count', '>', 0)
            ->whereNotNull('checkout_started_at')
            ->whereNull('checkout_abandoned_at')
            ->where(function ($query) use ($cutoff): void {
                $query->where('last_activity_at', '<', $cutoff)
                    ->orWhere(function ($fallback) use ($cutoff): void {
                        $fallback->whereNull('last_activity_at')
                            ->where('checkout_started_at', '<', $cutoff);
                    });
            });
    }
}
