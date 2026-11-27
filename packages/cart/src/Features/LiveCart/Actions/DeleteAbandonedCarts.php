<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\LiveCart\Actions;

use AIArmada\Cart\Features\LiveCart\Api\AbandonedCartCleanup;
use AIArmada\Cart\Features\LiveCart\Models\CartModel;
use AIArmada\Cart\Features\LiveCart\Persistence\CartOwnerScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class DeleteAbandonedCarts implements AbandonedCartCleanup
{
    public function owners(string $table): array
    {
        $this->assertTable($table);

        if (! CartModel::ownerScopingEnabled()) {
            return [];
        }

        if (! OwnerContext::isExplicitGlobal()) {
            throw new AuthorizationException('Cart owner enumeration requires explicit global owner context.');
        }

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
        $lastId = null;

        while (true) {
            $query = $this->query($table, $useExpired, $days, $now, $ownerType, $ownerId, $deleteMode);

            if ($lastId !== null) {
                $query->where('id', '>', $lastId);
            }

            $ids = $query->orderBy('id')->limit($batchSize)->pluck('id');

            if ($ids->isEmpty()) {
                return $processed;
            }

            $lastId = (string) $ids->last();

            $deleted = $this->query($table, $useExpired, $days, $now, $ownerType, $ownerId, $deleteMode)
                ->whereIn('id', $ids->all())
                ->delete();

            $processed += $deleted;
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

        if (CartModel::ownerScopingEnabled()) {
            $this->assertOwnerScopeMatches($ownerType, $ownerId);
            CartOwnerScope::applyForOwner($query, $ownerType, $ownerId);
        }

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

    private function assertOwnerScopeMatches(?string $ownerType, string | int | null $ownerId): void
    {
        if ($ownerType === null && $ownerId === null) {
            if (! OwnerContext::isExplicitGlobal()) {
                throw new AuthorizationException('Global cart cleanup requires explicit global owner context.');
            }

            return;
        }

        $ambient = OwnerContext::resolve();

        if ($ambient === null
            || $ambient->getMorphClass() !== $ownerType
            || (string) $ambient->getKey() !== (string) $ownerId
        ) {
            throw new AuthorizationException('Cart cleanup must run inside the owner scope being cleaned.');
        }
    }

    private function assertTable(string $table): void
    {
        if ($table !== config('cart.database.table', 'carts')) {
            throw new InvalidArgumentException('Cart cleanup may only access the configured cart table.');
        }
    }
}
