<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

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
        'clicks',
        'conversions',
        'is_active',
    ];

    protected $casts = [
        'clicks' => 'integer',
        'conversions' => 'integer',
        'is_active' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.links', 'affiliate_links');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

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
}
