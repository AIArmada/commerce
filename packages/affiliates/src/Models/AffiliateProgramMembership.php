<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Models\Concerns\ScopesByAffiliateOwner;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string $program_id
 * @property string|null $tier_id
 * @property MembershipStatus $status
 * @property Carbon $applied_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $expires_at
 * @property string|null $approved_by
 * @property array<string, mixed>|null $custom_terms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate $affiliate
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
        'custom_terms',
    ];

    protected $casts = [
        'status' => MembershipStatus::class,
        'applied_at' => 'datetime',
        'approved_at' => 'datetime',
        'expires_at' => 'datetime',
        'custom_terms' => 'array',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.program_memberships', 'affiliate_program_memberships');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
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

    public function approve(?string $approvedBy = null): void
    {
        $this->update([
            'status' => MembershipStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $approvedBy,
        ]);
    }

    public function reject(): void
    {
        $this->update([
            'status' => MembershipStatus::Rejected,
        ]);
    }

    public function suspend(): void
    {
        $this->update([
            'status' => MembershipStatus::Suspended,
        ]);
    }

    public function upgradeTier(AffiliateProgramTier $tier): void
    {
        if ($tier->program_id !== $this->program_id) {
            throw new RuntimeException('Selected tier does not belong to this membership program.');
        }

        $this->update([
            'tier_id' => $tier->id,
        ]);
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
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return;
        }

        if ($membership->program_id !== null && ! self::programExistsInCurrentOrGlobalScope($membership->program_id)) {
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
