<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Models;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleReference;
use AIArmada\Affiliates\Features\Programs\Api\ProgramJoinReference;
use AIArmada\Affiliates\Models\Concerns\ScopesByAffiliateOwner;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string $program_id
 * @property string|null $tier_id
 * @property MembershipStatus $status
 * @property Carbon $applied_at
 * @property Carbon|null $approved_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $suspended_at
 * @property Carbon|null $expires_at
 * @property string|null $approved_by
 * @property array<string, mixed>|null $custom_terms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AffiliateProgram $program
 * @property-read AffiliateProgramTier|null $tier
 */
class AffiliateProgramMembership extends Pivot
{
    use HasUuids;
    use ScopesByAffiliateOwner;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'affiliate_id',
        'program_id',
        'tier_id',
        'status',
        'applied_at',
        'approved_at',
        'expires_at',
        'approved_by',
        'rejected_at',
        'suspended_at',
        'custom_terms',
    ];

    protected $casts = [
        'status' => MembershipStatus::class,
        'applied_at' => 'immutable_datetime',
        'approved_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'rejected_at' => 'immutable_datetime',
        'suspended_at' => 'immutable_datetime',
        'custom_terms' => 'array',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.program_memberships', 'affiliate_program_memberships');
    }

    /**
     * @return BelongsTo<AffiliateProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'program_id');
    }

    /**
     * @return BelongsTo<AffiliateProgramTier, $this>
     */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgramTier::class, 'tier_id');
    }

    public function joinReference(AffiliateLifecycleReference $affiliate, AffiliateProgram $program): ProgramJoinReference
    {
        return new ProgramJoinReference(
            affiliate: $affiliate,
            programId: (string) $program->getKey(),
            programOwnerType: $program->owner_type,
            programOwnerId: $program->owner_id === null ? null : (string) $program->owner_id,
            membershipId: (string) $this->getKey(),
            membershipAffiliateId: $this->affiliate_id,
            membershipProgramId: $this->program_id,
            tierId: $this->tier_id,
        );
    }

    public function isActive(): bool
    {
        if ($this->status !== MembershipStatus::Approved) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isRejected(): bool
    {
        return $this->rejected_at !== null;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    protected static function booted(): void
    {
        static::creating(function (self $membership): void {
            self::guardProgramReferences($membership);
        });

        static::updating(function (self $membership): void {
            self::guardProgramReferences($membership);
        });
    }

    private static function guardProgramReferences(self $membership): void
    {
        if ((bool) config('affiliates.owner.enabled', false)
            && $membership->program_id !== null
            && ! self::programExistsInCurrentOrGlobalScope($membership->program_id)) {
            throw new AuthorizationException('Cross-tenant program reference is not allowed.');
        }

        if ($membership->tier_id === null) {
            return;
        }

        $tierQuery = AffiliateProgramTier::query()
            ->withoutGlobalScope('program_owner')
            ->whereKey($membership->tier_id);

        if ($membership->program_id !== null) {
            $tierQuery->where('program_id', $membership->program_id);
        }

        if (! $tierQuery->exists()) {
            throw new AuthorizationException('Cross-tenant or mismatched program tier reference is not allowed.');
        }
    }

    private static function programExistsInCurrentOrGlobalScope(string $programId): bool
    {
        if (AffiliateProgram::query()->whereKey($programId)->exists()) {
            return true;
        }

        $config = AffiliateProgram::ownerScopeConfig();

        return AffiliateProgram::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->whereKey($programId)
            ->whereNull($config->ownerTypeColumn)
            ->whereNull($config->ownerIdColumn)
            ->exists();
    }
}
