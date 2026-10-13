<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Models;

use AIArmada\Rewards\Database\Factories\RewardBadgeGrantFactory;
use AIArmada\Rewards\Enums\RewardBadgeStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Private badge grant. Grants are per-recipient, never public. A revoked
 * badge keeps its history row; a later re-grant appends a new seq row.
 * The single allowed mutation is granted -> revoked with reason/actor.
 *
 * @property RewardBadgeStatus $status
 * @property array<string, mixed>|null $evidence
 * @property string $id
 * @property string $recipient_type
 * @property string $recipient_id
 * @property string $family_key
 * @property string $badge_key
 * @property int $seq
 * @property string|null $program_id
 * @property string|null $award_id
 * @property string $granted_by_type
 * @property string|null $granted_by_id
 * @property string $grant_reason
 * @property array|null $evidence
 * @property CarbonImmutable $granted_at
 * @property string|null $revoked_by_type
 * @property string|null $revoked_by_id
 * @property string|null $revoke_reason
 * @property CarbonImmutable|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RewardAward $award
 */
class RewardBadgeGrant extends Model
{
    /** @use HasFactory<RewardBadgeGrantFactory> */
    use HasFactory;

    use HasUuids;

    public function getTable(): string
    {
        return config('rewards.database.tables.badge_grants', 'reward_badge_grants');
    }

    protected $fillable = [
        'recipient_type',
        'recipient_id',
        'family_key',
        'badge_key',
        'seq',
        'program_id',
        'award_id',
        'status',
        'granted_by_type',
        'granted_by_id',
        'grant_reason',
        'evidence',
        'granted_at',
        'revoked_by_type',
        'revoked_by_id',
        'revoke_reason',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'status' => RewardBadgeStatus::class,
            'evidence' => 'array',
            'granted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
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
        static::updating(static function (RewardBadgeGrant $grant): void {
            foreach ($grant->getDirty() as $field => $value) {
                if (! in_array($field, ['status', 'revoked_by_type', 'revoked_by_id', 'revoke_reason', 'revoked_at', 'updated_at'], true)) {
                    throw new LogicException("Reward badge grant [{$field}] is immutable.");
                }
            }

            if ($grant->isDirty('status')
                && ! ($grant->getOriginal('status') instanceof RewardBadgeStatus
                    && $grant->getOriginal('status') === RewardBadgeStatus::Granted
                    && $grant->status === RewardBadgeStatus::Revoked)
            ) {
                throw new LogicException('Reward badge grants only transition granted -> revoked.');
            }

            if ($grant->getOriginal('revoked_at') !== null && $grant->isDirty('revoked_at')) {
                throw new LogicException('Reward badge grant [revoked_at] is immutable once set.');
            }
        });

        static::deleting(static function (): void {
            throw new LogicException('Reward badge grants cannot be deleted; revoke them instead.');
        });
    }

    protected static function newFactory(): RewardBadgeGrantFactory
    {
        return RewardBadgeGrantFactory::new();
    }
}
