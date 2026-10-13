<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Models;

use AIArmada\Rewards\Database\Factories\RewardAdjustmentFactory;
use AIArmada\Rewards\Enums\RewardAdjustmentKind;
use AIArmada\Rewards\Enums\RewardAdjustmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only override/adjustment/dispute evidence.
 *
 * operation_key is the stable semantic identity (retries collapse). A
 * correction whose credited value was already consumed opens a dispute
 * obligation here instead of magically restoring value or debiting a
 * negative bank balance. Resolution is recorded, never rewritten: the single
 * allowed mutation is open -> resolved with actor/reason.
 *
 * @property RewardAdjustmentKind $kind
 * @property RewardAdjustmentStatus $status
 * @property array<string, mixed>|null $amount_snapshot
 * @property array<string, mixed>|null $evidence
 * @property string $id
 * @property string|null $award_id
 * @property string|null $appeal_id
 * @property string|null $linked_adjustment_id
 * @property string $operation_key
 * @property array|null $amount_snapshot
 * @property string $actor_type
 * @property string|null $actor_id
 * @property string $actor_role
 * @property string $reason
 * @property array|null $evidence
 * @property string|null $resolved_by_type
 * @property string|null $resolved_by_id
 * @property string|null $resolve_reason
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RewardAward $award
 * @property-read RewardAppeal $appeal
 */
class RewardAdjustment extends Model
{
    /** @use HasFactory<RewardAdjustmentFactory> */
    use HasFactory;

    use HasUuids;

    public function getTable(): string
    {
        return config('rewards.database.tables.adjustments', 'reward_adjustments');
    }

    protected $fillable = [
        'award_id',
        'appeal_id',
        'linked_adjustment_id',
        'kind',
        'status',
        'operation_key',
        'amount_snapshot',
        'actor_type',
        'actor_id',
        'actor_role',
        'reason',
        'evidence',
        'resolved_by_type',
        'resolved_by_id',
        'resolve_reason',
        'opened_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => RewardAdjustmentKind::class,
            'status' => RewardAdjustmentStatus::class,
            'amount_snapshot' => 'array',
            'evidence' => 'array',
            'opened_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<RewardAward, $this>
     */
    public function award(): BelongsTo
    {
        return $this->belongsTo(RewardAward::class, 'award_id');
    }

    /**
     * @return BelongsTo<RewardAppeal, $this>
     */
    public function appeal(): BelongsTo
    {
        return $this->belongsTo(RewardAppeal::class, 'appeal_id');
    }

    protected static function booted(): void
    {
        static::updating(static function (RewardAdjustment $adjustment): void {
            foreach ($adjustment->getDirty() as $field => $value) {
                if (! in_array($field, ['status', 'resolved_by_type', 'resolved_by_id', 'resolve_reason', 'resolved_at', 'updated_at'], true)) {
                    throw new LogicException("Reward adjustment [{$field}] is immutable evidence.");
                }
            }

            if ($adjustment->isDirty('status')
                && ! ($adjustment->getOriginal('status') === RewardAdjustmentStatus::Open
                    && $adjustment->status === RewardAdjustmentStatus::Resolved)
            ) {
                throw new LogicException('Reward adjustments only transition open -> resolved.');
            }

            if ($adjustment->getOriginal('resolved_at') !== null && $adjustment->isDirty('resolved_at')) {
                throw new LogicException('Reward adjustment [resolved_at] is immutable once set.');
            }
        });

        static::deleting(static function (): void {
            throw new LogicException('Reward adjustments cannot be deleted.');
        });
    }

    protected static function newFactory(): RewardAdjustmentFactory
    {
        return RewardAdjustmentFactory::new();
    }
}
