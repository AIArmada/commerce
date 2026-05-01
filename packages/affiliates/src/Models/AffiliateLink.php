<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Models\Concerns\ScopesByAffiliateOwner;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tracking links created by affiliates for campaigns.
 *
 * @property string $id
 * @property string $affiliate_id
 * @property string|null $program_id
 * @property string $destination_url
 * @property string $tracking_url
 * @property string|null $short_url
 * @property string|null $custom_slug
 * @property string|null $campaign
 * @property string|null $sub_id
 * @property string|null $sub_id_2
 * @property string|null $sub_id_3
 * @property string|null $subject_type
 * @property string|null $subject_identifier
 * @property string|null $subject_instance
 * @property string|null $subject_title_snapshot
 * @property array<string, mixed>|null $subject_metadata
 * @property int $clicks
 * @property int $conversions
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate $affiliate
 * @property-read AffiliateProgram|null $program
 */
class AffiliateLink extends Model
{
    use HasUuids;
    use ScopesByAffiliateOwner;

    protected $fillable = [
        'affiliate_id',
        'program_id',
        'destination_url',
        'tracking_url',
        'short_url',
        'custom_slug',
        'campaign',
        'sub_id',
        'sub_id_2',
        'sub_id_3',
        'subject_type',
        'subject_identifier',
        'subject_instance',
        'subject_title_snapshot',
        'subject_metadata',
        'clicks',
        'conversions',
        'is_active',
    ];

    protected $casts = [
        'subject_metadata' => 'array',
        'clicks' => 'integer',
        'conversions' => 'integer',
        'is_active' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.links', 'affiliate_links');
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

    public function incrementClicks(): void
    {
        $this->increment('clicks');
    }

    public function incrementConversions(): void
    {
        $this->increment('conversions');
    }

    public function getConversionRate(): float
    {
        if ($this->clicks === 0) {
            return 0.0;
        }

        return ($this->conversions / $this->clicks) * 100;
    }

    public function getDisplayUrl(): string
    {
        return $this->short_url ?? $this->tracking_url;
    }

    protected static function booted(): void
    {
        static::creating(function (self $link): void {
            self::guardProgramReference($link);
        });

        static::updating(function (self $link): void {
            self::guardProgramReference($link);
        });
    }

    private static function guardProgramReference(self $link): void
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return;
        }

        if ($link->program_id === null) {
            return;
        }

        if (! self::programExistsInCurrentOrGlobalScope($link->program_id)) {
            throw new AuthorizationException('Cross-tenant program reference is not allowed.');
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
