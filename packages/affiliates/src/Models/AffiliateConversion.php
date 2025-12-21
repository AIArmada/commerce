<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Enums\ConversionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $metadata
 */
class AffiliateConversion extends Model
{
    use HasUuids;

    protected $fillable = [
        'affiliate_id',
        'affiliate_code',
        'affiliate_attribution_id',
        'affiliate_payout_id',
        'cart_identifier',
        'cart_instance',
        'voucher_code',
        'order_reference',
        'subtotal_minor',
        'total_minor',
        'commission_minor',
        'commission_currency',
        'status',
        'channel',
        'metadata',
        'owner_type',
        'owner_id',
        'occurred_at',
        'approved_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
        'approved_at' => 'datetime',
        'status' => ConversionStatus::class,
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.conversions', parent::getTable());
    }

    /**
     * @return BelongsTo<Affiliate, self>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return BelongsTo<AffiliateAttribution, self>
     */
    public function attribution(): BelongsTo
    {
        return $this->belongsTo(AffiliateAttribution::class, 'affiliate_attribution_id');
    }

    /**
     * @return BelongsTo<AffiliatePayout, self>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'affiliate_payout_id');
    }

    protected static function booted(): void
    {
        // This model has no child relationships requiring cascade deletes
        // Kept for consistency with other models in the package
    }
}
