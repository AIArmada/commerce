<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Enums\MembershipStatus;
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

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'program_id');
    }

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
        $this->update([
            'tier_id' => $tier->id,
        ]);
    }
}
