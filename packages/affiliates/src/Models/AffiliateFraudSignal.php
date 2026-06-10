<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\Concerns\ScopesByAffiliateOwner;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string|null $conversion_id
 * @property string|null $touchpoint_id
 * @property string $rule_code
 * @property int $risk_points
 * @property FraudSeverity $severity
 * @property string $description
 * @property array<string, mixed>|null $evidence
 * @property FraudSignalStatus $status
 * @property Carbon $detected_at
 * @property Carbon|null $reviewed_at
 * @property string|null $reviewed_by
 * @property CarbonImmutable|null $dismissed_at
 * @property CarbonImmutable|null $confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate $affiliate
 * @property-read AffiliateConversion|null $conversion
 * @property-read AffiliateTouchpoint|null $touchpoint
 */
class AffiliateFraudSignal extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasUuids;
    use LogsCommerceActivity;
    use ScopesByAffiliateOwner;

    protected $fillable = [
        'affiliate_id',
        'conversion_id',
        'touchpoint_id',
        'rule_code',
        'risk_points',
        'severity',
        'description',
        'evidence',
        'status',
        'detected_at',
        'reviewed_at',
        'reviewed_by',
        'dismissed_at',
        'confirmed_at',
    ];

    protected $casts = [
        'risk_points' => 'integer',
        'severity' => FraudSeverity::class,
        'status' => FraudSignalStatus::class,
        'evidence' => 'array',
        'detected_at' => 'immutable_datetime',
        'reviewed_at' => 'immutable_datetime',
        'dismissed_at' => 'immutable_datetime',
        'confirmed_at' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.fraud_signals', 'affiliate_fraud_signals');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return BelongsTo<AffiliateConversion, $this>
     */
    public function conversion(): BelongsTo
    {
        return $this->belongsTo(AffiliateConversion::class);
    }

    /**
     * @return BelongsTo<AffiliateTouchpoint, $this>
     */
    public function touchpoint(): BelongsTo
    {
        return $this->belongsTo(AffiliateTouchpoint::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', FraudSignalStatus::Detected);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', FraudSignalStatus::Confirmed);
    }

    public function scopeHighSeverity(Builder $query): Builder
    {
        return $query->whereIn('severity', [FraudSeverity::High, FraudSeverity::Critical]);
    }

    public function markAsReviewed(?string $reviewedBy = null): void
    {
        $this->update([
            'status' => FraudSignalStatus::Reviewed,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewedBy,
        ]);
    }

    public function dismiss(?string $reviewedBy = null): void
    {
        $this->update([
            'status' => FraudSignalStatus::Dismissed,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewedBy,
            'dismissed_at' => now(),
        ]);
    }

    public function confirm(?string $reviewedBy = null): void
    {
        $this->update([
            'status' => FraudSignalStatus::Confirmed,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewedBy,
            'confirmed_at' => now(),
        ]);
    }

    protected function getActivityLogName(): string
    {
        return 'affiliates';
    }
}
