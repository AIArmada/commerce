<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Models;

use AIArmada\Referrals\Database\Factories\ReferralInviteFactory;
use AIArmada\Referrals\Enums\ReferralInviteStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * @property string $id
 * @property string $program_id
 * @property string|null $referrer_type
 * @property string|null $referrer_id
 * @property string $invitee_hash
 * @property string|null $invitee_contact_encrypted
 * @property string $token_hash
 * @property ReferralInviteStatus $status
 * @property string|null $accepted_by_type
 * @property string|null $accepted_by_id
 * @property string|null $qualification_business_key
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $qualified_at
 * @property CarbonImmutable|null $rewarded_at
 * @property CarbonImmutable|null $expired_at
 * @property CarbonImmutable|null $revoked_at
 * @property array<string, mixed>|null $metadata
 * @property-read ReferralProgram $program
 * @property-read Collection<int, ReferralReward> $rewards
 */
class ReferralInvite extends Model
{
    /** @use HasFactory<ReferralInviteFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'program_id',
        'referrer_type',
        'referrer_id',
        'invitee_hash',
        'invitee_contact_encrypted',
        'metadata',
    ];

    protected $hidden = [
        'invitee_contact_encrypted',
        'token_hash',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invite): void {
            $invite->status ??= ReferralInviteStatus::Pending;
        });

        static::deleting(function (self $invite): void {
            foreach ($invite->rewards()->get() as $reward) {
                $reward->delete();
            }
        });

        static::updating(function (self $invite): void {
            foreach (['program_id', 'referrer_type', 'referrer_id', 'invitee_hash', 'invitee_contact_encrypted', 'token_hash'] as $attribute) {
                if ($invite->isDirty($attribute)) {
                    throw new LogicException(sprintf('Referral invite attribute [%s] is immutable.', $attribute));
                }
            }

            foreach (['accepted_by_type', 'accepted_by_id', 'qualification_business_key', 'accepted_at', 'qualified_at', 'expired_at', 'revoked_at'] as $attribute) {
                if ($invite->getRawOriginal($attribute) !== null && $invite->isDirty($attribute)) {
                    throw new LogicException(sprintf('Referral invite lifecycle attribute [%s] cannot be changed after it is set.', $attribute));
                }
            }

            $rewardedToQualified = false;

            if ($invite->isDirty('status')) {
                $current = ReferralInviteStatus::from((string) $invite->getRawOriginal('status'));
                $next = $invite->status;
                $allowed = match ($current) {
                    ReferralInviteStatus::Pending => [ReferralInviteStatus::Accepted, ReferralInviteStatus::Expired, ReferralInviteStatus::Revoked],
                    ReferralInviteStatus::Accepted => [ReferralInviteStatus::Qualified, ReferralInviteStatus::Expired, ReferralInviteStatus::Revoked],
                    ReferralInviteStatus::Qualified => [ReferralInviteStatus::Rewarded],
                    ReferralInviteStatus::Rewarded => [ReferralInviteStatus::Qualified],
                    ReferralInviteStatus::Expired, ReferralInviteStatus::Revoked => [],
                };

                if (! in_array($next, $allowed, true)) {
                    throw new LogicException(sprintf('Referral invite cannot transition from [%s] to [%s].', $current->value, $next->value));
                }

                $rewardedToQualified = $current === ReferralInviteStatus::Rewarded
                    && $next === ReferralInviteStatus::Qualified;
            }

            if ($rewardedToQualified
                && $invite->getRawOriginal('rewarded_at') !== null
                && (! $invite->isDirty('rewarded_at') || $invite->rewarded_at !== null)) {
                throw new LogicException('A rewarded invite must clear rewarded_at when returning to qualified.');
            }

            if ($invite->getRawOriginal('rewarded_at') !== null && $invite->isDirty('rewarded_at')) {
                if (! $rewardedToQualified || $invite->rewarded_at !== null) {
                    throw new LogicException('Referral invite lifecycle attribute [rewarded_at] can only be cleared when a rewarded invite returns to qualified.');
                }
            }
        });
    }

    public function getTable(): string
    {
        return (string) config('referrals.database.tables.invites', 'referral_invites');
    }

    protected function casts(): array
    {
        return [
            'invitee_contact_encrypted' => 'encrypted',
            'status' => ReferralInviteStatus::class,
            'accepted_at' => 'immutable_datetime',
            'qualified_at' => 'immutable_datetime',
            'rewarded_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
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
    public function referrer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function acceptedBy(): MorphTo
    {
        return $this->morphTo('acceptedBy', 'accepted_by_type', 'accepted_by_id');
    }

    /**
     * @return HasMany<ReferralReward, $this>
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class, 'invite_id');
    }

    public function isExpiredAt(CarbonImmutable $at): bool
    {
        return $this->status === ReferralInviteStatus::Expired
            || ($this->expired_at !== null && $this->expired_at->lessThanOrEqualTo($at));
    }

    public function rotateTokenHash(string $hash): void
    {
        if (! $this->exists
            || $this->status !== ReferralInviteStatus::Pending
            || $this->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Only pending referral invites can rotate their token hash inside a transaction.');
        }

        $updated = $this->newQuery()
            ->whereKey($this->getKey())
            ->where('status', ReferralInviteStatus::Pending->value)
            ->update(['token_hash' => $hash]);

        if ($updated !== 1) {
            throw new LogicException('The pending referral invite could not rotate its token hash.');
        }

        $this->setAttribute('token_hash', $hash);
        $this->syncOriginalAttribute('token_hash');
    }

    protected static function newFactory(): ReferralInviteFactory
    {
        return ReferralInviteFactory::new();
    }
}
