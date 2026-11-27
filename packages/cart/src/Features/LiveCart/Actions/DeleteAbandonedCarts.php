<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\LiveCart\Actions;

use AIArmada\Cart\Features\LiveCart\Api\AbandonedCartCleanup;
use AIArmada\Cart\Features\LiveCart\Models\CartModel;
use AIArmada\Cart\Features\LiveCart\Persistence\CartOwnerScope;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class DeleteAbandonedCarts implements AbandonedCartCleanup
{
    public function owners(string $table): array
    {
        $this->assertTable($table);
        $columns = OwnerTupleColumns::forModelClass(CartModel::class);

        return DB::table($table)
            ->select([$columns->ownerTypeColumn, $columns->ownerIdColumn])
            ->distinct()->get()->map(fn (object $row): array => (array) $row)->values()->all();
    }

    public function count(
        string $table,
        bool $useExpired,
        int $days,
        CarbonInterface $now,
        ?string $ownerType,
        string | int | null $ownerId,
        bool $deleteMode,
    ): int {
        return $this->query($table, $useExpired, $days, $now, $ownerType, $ownerId, $deleteMode)->count();
    }

    public function execute(
        string $table,
        bool $useExpired,
        int $days,
        CarbonInterface $now,
        int $batchSize,
        ?string $ownerType,
        string | int | null $ownerId,
        bool $deleteMode,
    ): int {
        if ($batchSize < 1) {
            throw new InvalidArgumentException('Cart cleanup batch size must be positive.');
        }

        $processed = 0;

        while (true) {
            $query = $this->query($table, $useExpired, $days, $now, $ownerType, $ownerId, $deleteMode);
            $ids = $query->orderBy('id')->limit($batchSize)->pluck('id');

            if ($ids->isEmpty()) {
                return $processed;
            }

            // Reapply the selection predicate at deletion time so a cart
            // updated between selection and deletion is not destroyed.
            $deleted = $this->query($table, $useExpired, $days, $now, $ownerType, $ownerId, $deleteMode)
                ->whereIn('id', $ids->all())
                ->delete();

            $processed += $deleted;

            if ($deleted < $ids->count()) {
                return $processed;
            }
        }
    }

    private function query(
        string $table,
        bool $useExpired,
        int $days,
        CarbonInterface $now,
        ?string $ownerType,
        string | int | null $ownerId,
        bool $deleteMode,
    ): Builder {
        $this->assertTable($table);

        if ($days < 0) {
            throw new InvalidArgumentException('Cart cleanup age must not be negative.');
        }

        $query = DB::table($table);
        CartOwnerScope::applyForOwner($query, $ownerType, $ownerId);

        if ($deleteMode) {
            return $query->whereNotNull('abandoned_at');
        }

        if ($useExpired) {
            return $query->whereNull('expired_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now);
        }

        return $query->whereNull('abandoned_at')
            ->where('updated_at', '<', $now->copy()->subDays($days));
    }

    private function assertTable(string $table): void
    {
        if ($table !== config('cart.database.table', 'carts')) {
            throw new InvalidArgumentException('Cart cleanup may only access the configured cart table.');
        }
    }
}
