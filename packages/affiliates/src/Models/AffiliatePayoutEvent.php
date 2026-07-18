<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $affiliate_payout_id
 * @property string|null $from_status
 * @property string $to_status
 * @property array<string, mixed>|null $metadata
 * @property string|null $notes
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read AffiliatePayout $payout
 */
class AffiliatePayoutEvent extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasUuids;
    use LogsCommerceActivity;

    protected $fillable = [
        'affiliate_payout_id',
        'from_status',
        'to_status',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.payout_events', parent::getTable());
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'affiliate_payout_id');
    }

    protected function getActivityLogName(): string
    {
        return 'affiliates';
    }
}
