<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Models\Concerns\ScopesByAffiliateOwner;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string $document_type
 * @property int $tax_year
 * @property string $status
 * @property int $total_amount_minor
 * @property string $currency
 * @property string|null $document_path
 * @property string|null $notes
 * @property Carbon|null $generated_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate $affiliate
 */
class AffiliateTaxDocument extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasUuids;
    use LogsCommerceActivity;
    use ScopesByAffiliateOwner;

    protected $fillable = [
        'affiliate_id',
        'document_type',
        'tax_year',
        'status',
        'total_amount_minor',
        'currency',
        'document_path',
        'notes',
        'generated_at',
        'sent_at',
    ];

    public function getAuditInclude(): array
    {
        return [
            'affiliate_id',
            'document_type',
            'tax_year',
            'status',
            'total_amount_minor',
            'currency',
            'generated_at',
            'sent_at',
        ];
    }

    protected $casts = [
        'tax_year' => 'integer',
        'total_amount_minor' => 'integer',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.tax_documents', 'affiliate_tax_documents');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }

    /**
     * @return array<int, string>
     */
    protected function getLoggableAttributes(): array
    {
        return $this->getAuditInclude();
    }

    protected function getActivityLogName(): string
    {
        return 'affiliates';
    }
}
