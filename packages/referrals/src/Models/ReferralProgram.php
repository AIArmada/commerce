<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Models;

use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Referrals\Database\Factories\ReferralProgramFactory;
use AIArmada\Referrals\Enums\ReferralProgramStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property ReferralProgramStatus $status
 * @property string $qualifier_outcome
 * @property int|null $invite_expiry_days
 * @property int|null $max_invites_per_referrer
 * @property int|null $max_qualified_per_referrer_per_month
 * @property array<string, mixed>|null $referrer_reward
 * @property array<string, mixed>|null $referee_reward
 * @property string $currency
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property array<string, mixed>|null $metadata
 * @property-read Collection<int, ReferralInvite> $invites
 * @property-read Collection<int, ReferralReward> $rewards
 */
class ReferralProgram extends Model
{
    /** @use HasFactory<ReferralProgramFactory> */
    use HasFactory;

    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'referrals.owner';

    protected static bool $ownerScopeEnabledByDefault = true;

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return OwnerScopeConfig::fromConfig(
            'referrals.owner',
            enabledDefault: true,
            includeGlobalDefault: false,
            ownerTypeColumn: (string) config('referrals.owner.owner_type_column', 'owner_type'),
            ownerIdColumn: (string) config('referrals.owner.owner_id_column', 'owner_id'),
            autoAssignOnCreateDefault: true,
        );
    }

    protected $fillable = [
        'name',
        'slug',
        'status',
        'qualifier_outcome',
        'invite_expiry_days',
        'max_invites_per_referrer',
        'max_qualified_per_referrer_per_month',
        'referrer_reward',
        'referee_reward',
        'currency',
        'starts_at',
        'ends_at',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $program): void {
            $program->status ??= ReferralProgramStatus::Draft;
        });

        static::deleting(function (self $program): void {
            foreach ($program->invites()->get() as $invite) {
                $invite->delete();
            }
        });
    }

    public function getTable(): string
    {
        return (string) config('referrals.database.tables.programs', 'referral_programs');
    }

    protected function casts(): array
    {
        return [
            'status' => ReferralProgramStatus::class,
            'invite_expiry_days' => 'integer',
            'max_invites_per_referrer' => 'integer',
            'max_qualified_per_referrer_per_month' => 'integer',
            'referrer_reward' => 'array',
            'referee_reward' => 'array',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<ReferralInvite, $this>
     */
    public function invites(): HasMany
    {
        return $this->hasMany(ReferralInvite::class, 'program_id');
    }

    /**
     * @return HasMany<ReferralReward, $this>
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class, 'program_id');
    }

    public function isActiveAt(CarbonImmutable $at): bool
    {
        return $this->status === ReferralProgramStatus::Active
            && ($this->starts_at === null || $this->starts_at->lessThanOrEqualTo($at))
            && ($this->ends_at === null || $this->ends_at->greaterThan($at));
    }

    protected static function newFactory(): ReferralProgramFactory
    {
        return ReferralProgramFactory::new();
    }
}
