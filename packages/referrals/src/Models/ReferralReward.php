<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Models;

use AIArmada\Referrals\Database\Factories\ReferralRewardFactory;
use AIArmada\Referrals\Enums\ReferralRewardSide;
use AIArmada\Referrals\Enums\ReferralRewardStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * @property string $id
 * @property string $invite_id
 * @property string $program_id
 * @property ReferralRewardSide $side
 * @property string|null $beneficiary_type
 * @property string|null $beneficiary_id
 * @property string $driver
 * @property int|null $amount_minor
 * @property int|null $points
 * @property string|null $currency
 * @property array<string, mixed> $spec
 * @property ReferralRewardStatus $status
 * @property string $idempotency_key
 * @property string|null $external_reference
 * @property CarbonImmutable|null $fulfilled_at
 * @property CarbonImmutable|null $reversed_at
 * @property array<string, mixed>|null $metadata
 * @property-read ReferralInvite $invite
 * @property-read ReferralProgram $program
 */
class ReferralReward extends Model
{
    /** @use HasFactory<ReferralRewardFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'metadata',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $reward): void {
            $reward->status ??= ReferralRewardStatus::Pending;
        });

        static::updating(function (self $reward): void {
            foreach (['invite_id', 'program_id', 'side', 'beneficiary_type', 'beneficiary_id', 'driver', 'amount_minor', 'points', 'currency', 'spec', 'idempotency_key'] as $attribute) {
                if ($reward->isDirty($attribute)) {
                    throw new LogicException(sprintf('Referral reward attribute [%s] is frozen.', $attribute));
                }
            }

            foreach (['fulfilled_at', 'reversed_at'] as $attribute) {
                if ($reward->getRawOriginal($attribute) !== null && $reward->isDirty($attribute)) {
                    throw new LogicException(sprintf('Referral reward lifecycle attribute [%s] cannot be changed after it is set.', $attribute));
                }
            }

            if ($reward->isDirty('external_reference') && $reward->getRawOriginal('external_reference') !== null) {
                throw new LogicException('A referral reward external reference cannot be replaced.');
            }

            if ($reward->isDirty('status')) {
                $current = ReferralRewardStatus::from((string) $reward->getRawOriginal('status'));
                $next = $reward->status;
                $allowed = match ($current) {
                    ReferralRewardStatus::Pending => [ReferralRewardStatus::Fulfilled, ReferralRewardStatus::Failed],
                    ReferralRewardStatus::Failed => [ReferralRewardStatus::Fulfilled, ReferralRewardStatus::Failed],
                    ReferralRewardStatus::Fulfilled => [ReferralRewardStatus::Reversed],
                    ReferralRewardStatus::Reversed => [],
                };

                if (! in_array($next, $allowed, true)) {
                    throw new LogicException(sprintf('Referral reward cannot transition from [%s] to [%s].', $current->value, $next->value));
                }
            }
        });
    }

    public function getTable(): string
    {
        return (string) config('referrals.database.tables.rewards', 'referral_rewards');
    }

    protected function casts(): array
    {
        return [
            'side' => ReferralRewardSide::class,
            'amount_minor' => 'integer',
            'points' => 'integer',
            'spec' => 'array',
            'status' => ReferralRewardStatus::class,
            'fulfilled_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ReferralInvite, $this>
     */
    public function invite(): BelongsTo
    {
        return $this->belongsTo(ReferralInvite::class, 'invite_id');
    }

    /**
     * @return BelongsTo<ReferralProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(ReferralProgram::class, 'program_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function beneficiary(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): ReferralRewardFactory
    {
        return ReferralRewardFactory::new();
    }
}
