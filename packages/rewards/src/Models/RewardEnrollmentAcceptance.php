<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Models;

use AIArmada\Rewards\Database\Factories\RewardEnrollmentAcceptanceFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Immutable acceptance row. Reacceptance always appends a new row (next seq),
 * even for identical terms — no unique conflict. Effective terms at earning
 * time T are the latest acceptance with accepted_at <= T.
 *
 * @property string $id
 * @property string $enrollment_id
 * @property int $seq
 * @property string $policy_version_id
 * @property string $material_terms_hash
 * @property string $terms_label
 * @property array $accepted_snapshot
 * @property CarbonImmutable $accepted_at
 * @property string|null $accepted_via
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RewardEnrollment $enrollment
 * @property-read RewardProgramVersion $policyVersion
 */
class RewardEnrollmentAcceptance extends Model
{
    /** @use HasFactory<RewardEnrollmentAcceptanceFactory> */
    use HasFactory;

    use HasUuids;

    public function getTable(): string
    {
        return config('rewards.database.tables.enrollment_acceptances', 'reward_enrollment_acceptances');
    }

    protected $fillable = [
        'enrollment_id',
        'seq',
        'policy_version_id',
        'material_terms_hash',
        'terms_label',
        'accepted_snapshot',
        'accepted_at',
        'accepted_via',
    ];

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'accepted_snapshot' => 'array',
            'accepted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<RewardEnrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(RewardEnrollment::class, 'enrollment_id');
    }

    /**
     * @return BelongsTo<RewardProgramVersion, $this>
     */
    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(RewardProgramVersion::class, 'policy_version_id');
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Reward enrollment acceptances are immutable.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Reward enrollment acceptances cannot be deleted.');
        });
    }

    protected static function newFactory(): RewardEnrollmentAcceptanceFactory
    {
        return RewardEnrollmentAcceptanceFactory::new();
    }
}
