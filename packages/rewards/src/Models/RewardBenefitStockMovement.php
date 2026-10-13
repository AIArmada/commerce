<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Models;

use AIArmada\Rewards\Database\Factories\RewardBenefitStockMovementFactory;
use AIArmada\Rewards\Enums\RewardBenefitStockKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only benefit stock movement. Available units derive from sums
 * only: capacity + restock - reserved - consume, where
 * reserved = reserve - release - consume. Restock exists only for
 * revoked-unused units returning to the pool. Rows are never updated or
 * deleted.
 *
 * @property RewardBenefitStockKind $kind
 * @property string $id
 * @property string $movement_key
 * @property string $item_id
 * @property string|null $grant_id
 * @property int $quantity
 * @property string $actor_type
 * @property string|null $actor_id
 * @property string $actor_role
 * @property CarbonImmutable $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RewardBenefitStockMovement extends Model
{
    /** @use HasFactory<RewardBenefitStockMovementFactory> */
    use HasFactory;

    use HasUuids;

    public function getTable(): string
    {
        return config('rewards.database.tables.benefit_stock_movements', 'reward_benefit_stock_movements');
    }

    protected $fillable = [
        'movement_key',
        'item_id',
        'kind',
        'grant_id',
        'quantity',
        'actor_type',
        'actor_id',
        'actor_role',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => RewardBenefitStockKind::class,
            'quantity' => 'integer',
            'decided_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Reward benefit stock movements are append-only.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Reward benefit stock movements cannot be deleted.');
        });
    }

    protected static function newFactory(): RewardBenefitStockMovementFactory
    {
        return RewardBenefitStockMovementFactory::new();
    }
}
