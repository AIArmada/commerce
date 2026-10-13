<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Models;

use AIArmada\Rewards\Database\Factories\RewardAwardEntryFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only award decision entry. One row per transition (seq), carrying
 * actor/reason/evidence plus the source operation id for replay detection.
 * Never updated or deleted.
 *
 * @property array<string, mixed>|null $evidence
 * @property string $id
 * @property string $award_id
 * @property int $seq
 * @property string $from_status
 * @property string $to_status
 * @property string $actor_type
 * @property string|null $actor_id
 * @property string $actor_role
 * @property string $reason
 * @property array|null $evidence
 * @property string|null $source_operation_id
 * @property CarbonImmutable $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RewardAward $award
 */
class RewardAwardEntry extends Model
{
    /** @use HasFactory<RewardAwardEntryFactory> */
    use HasFactory;

    use HasUuids;

    public function getTable(): string
    {
        return config('rewards.database.tables.award_entries', 'reward_award_entries');
    }

    protected $fillable = [
        'award_id',
        'seq',
        'from_status',
        'to_status',
        'actor_type',
        'actor_id',
        'actor_role',
        'reason',
        'evidence',
        'source_operation_id',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'evidence' => 'array',
            'decided_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<RewardAward, $this>
     */
    public function award(): BelongsTo
    {
        return $this->belongsTo(RewardAward::class, 'award_id');
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Reward award entries are append-only.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Reward award entries cannot be deleted.');
        });
    }

    protected static function newFactory(): RewardAwardEntryFactory
    {
        return RewardAwardEntryFactory::new();
    }
}
