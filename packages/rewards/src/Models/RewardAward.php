<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Models;

use AIArmada\Rewards\Database\Factories\RewardAwardFactory;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Canonical entitlement award.
 *
 * Uniqueness is (recipient, source, family, component): the policy version is
 * frozen evidence, never part of uniqueness, so version switches and retries
 * return the first award instead of minting duplicates. Status transitions
 * stamp dedicated immutable timestamp columns; every transition appends a
 * RewardAwardEntry with actor/reason/evidence.
 *
 * @property RewardAwardStatus $status
 * @property RewardComponent $component
 * @property RewardBeneficiaryRole $beneficiary_role
 * @property array<string, mixed> $amount_snapshot
 * @property array<string, mixed> $policy_snapshot
 * @property array<string, mixed> $consent_snapshot
 * @property array<string, mixed> $source_snapshot
 * @property string $id
 * @property string $program_id
 * @property string|null $policy_version_id
 * @property string|null $enrollment_id
 * @property string|null $acceptance_id
 * @property string $recipient_type
 * @property string $recipient_id
 * @property string $source_business_key
 * @property string $family_key
 * @property array $amount_snapshot
 * @property array $policy_snapshot
 * @property array $consent_snapshot
 * @property array $source_snapshot
 * @property int $obligation_epoch
 * @property int $obligated_minor
 * @property CarbonImmutable|null $held_at
 * @property CarbonImmutable|null $pending_review_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $fulfilled_at
 * @property CarbonImmutable|null $reversed_at
 * @property CarbonImmutable|null $corrected_at
 * @property CarbonImmutable|null $acknowledged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RewardProgram $program
 * @property-read RewardProgramVersion $policyVersion
 * @property-read Collection<int, RewardAwardEntry> $entries
 */
class RewardAward extends Model
{
    /** @use HasFactory<RewardAwardFactory> */
    use HasFactory;

    use HasUuids;

    public function getTable(): string
    {
        return config('rewards.database.tables.awards', 'reward_awards');
    }

    protected $fillable = [
        'program_id',
        'policy_version_id',
        'enrollment_id',
        'acceptance_id',
        'recipient_type',
        'recipient_id',
        'beneficiary_role',
        'source_business_key',
        'family_key',
        'component',
        'status',
        'amount_snapshot',
        'policy_snapshot',
        'consent_snapshot',
        'source_snapshot',
        'held_at',
        'pending_review_at',
        'approved_at',
        'rejected_at',
        'fulfilled_at',
        'reversed_at',
        'corrected_at',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'beneficiary_role' => RewardBeneficiaryRole::class,
            'component' => RewardComponent::class,
            'status' => RewardAwardStatus::class,
            'amount_snapshot' => 'array',
            'policy_snapshot' => 'array',
            'consent_snapshot' => 'array',
            'source_snapshot' => 'array',
            'obligation_epoch' => 'integer',
            'obligated_minor' => 'integer',
            'held_at' => 'immutable_datetime',
            'pending_review_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'fulfilled_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
            'corrected_at' => 'immutable_datetime',
            'acknowledged_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<RewardProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(RewardProgram::class, 'program_id');
    }

    /**
     * @return BelongsTo<RewardProgramVersion, $this>
     */
    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(RewardProgramVersion::class, 'policy_version_id');
    }

    /**
     * @return HasMany<RewardAwardEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(RewardAwardEntry::class, 'award_id')->orderBy('seq');
    }

    protected static function booted(): void
    {
        static::updating(static function (RewardAward $award): void {
            foreach ([
                'program_id', 'policy_version_id', 'enrollment_id', 'acceptance_id',
                'recipient_type', 'recipient_id', 'beneficiary_role', 'source_business_key',
                'family_key', 'component', 'amount_snapshot', 'policy_snapshot',
                'consent_snapshot', 'source_snapshot',
            ] as $field) {
                if ($award->isDirty($field)) {
                    throw new LogicException("Reward award [{$field}] is frozen provenance and cannot be rewritten.");
                }
            }

            foreach ([
                'held_at', 'pending_review_at', 'approved_at', 'rejected_at',
                'fulfilled_at', 'reversed_at', 'corrected_at', 'acknowledged_at',
            ] as $field) {
                if ($award->getOriginal($field) !== null && $award->isDirty($field)) {
                    throw new LogicException("Reward award [{$field}] is an immutable transition timestamp.");
                }
            }

            if ($award->isDirty('status')) {
                $original = $award->getOriginal('status');
                $from = $original instanceof RewardAwardStatus
                    ? $original
                    : RewardAwardStatus::tryFrom((string) $original);

                if (! $from instanceof RewardAwardStatus || ! $from->canTransitionTo($award->status)) {
                    throw new LogicException('Invalid reward award status transition.');
                }
            }
        });

        static::deleting(static function (): void {
            throw new LogicException('Reward awards cannot be deleted; reverse or correct them instead.');
        });
    }

    protected static function newFactory(): RewardAwardFactory
    {
        return RewardAwardFactory::new();
    }
}
