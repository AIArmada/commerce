<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Models;

use AIArmada\Rewards\Database\Factories\RewardMilestoneContributionFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One contributing fact inside a milestone cycle. Unique (cycle, source):
 * the same source never counts twice. Corrections revoke (revoked_at +
 * reason) instead of deleting, so history is retained and recomputation is
 * auditable.
 *
 * @property array<string, mixed> $source_snapshot
 * @property string $id
 * @property string $cycle_id
 * @property string $source_business_key
 * @property string $contributor_type
 * @property string $contributor_id
 * @property string $contributor_role
 * @property string $outcome
 * @property array $source_snapshot
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable $contributed_at
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoke_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RewardMilestoneCycle $cycle
 */
class RewardMilestoneContribution extends Model
{
    /** @use HasFactory<RewardMilestoneContributionFactory> */
    use HasFactory;

    use HasUuids;

    public function getTable(): string
    {
        return config('rewards.database.tables.milestone_contributions', 'reward_milestone_contributions');
    }

    protected $fillable = [
        'cycle_id',
        'source_business_key',
        'contributor_type',
        'contributor_id',
        'contributor_role',
        'outcome',
        'source_snapshot',
        'occurred_at',
        'contributed_at',
        'revoked_at',
        'revoke_reason',
    ];

    protected function casts(): array
    {
        return [
            'source_snapshot' => 'array',
            'occurred_at' => 'immutable_datetime',
            'contributed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<RewardMilestoneCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(RewardMilestoneCycle::class, 'cycle_id');
    }

    protected static function booted(): void
    {
        static::updating(static function (RewardMilestoneContribution $contribution): void {
            foreach ($contribution->getDirty() as $field => $value) {
                if (! in_array($field, ['revoked_at', 'revoke_reason', 'updated_at'], true)) {
                    throw new LogicException("Reward milestone contribution [{$field}] is immutable.");
                }
            }

            if ($contribution->getOriginal('revoked_at') !== null && $contribution->isDirty('revoked_at')) {
                throw new LogicException('Reward milestone contribution [revoked_at] can only be set once.');
            }

            if ($contribution->isDirty('revoke_reason') && $contribution->revoked_at === null) {
                throw new LogicException('Reward milestone contribution revoke_reason requires revoked_at.');
            }
        });

        static::deleting(static function (): void {
            throw new LogicException('Reward milestone contributions cannot be deleted; revoke them instead.');
        });
    }

    protected static function newFactory(): RewardMilestoneContributionFactory
    {
        return RewardMilestoneContributionFactory::new();
    }
}
