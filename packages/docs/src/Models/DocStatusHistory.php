<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Docs\States\DocStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $doc_id
 * @property DocStatus $status
 * @property string|null $notes
 * @property string|null $changed_by
 * @property string $changed_by_type
 * @property CarbonImmutable $created_at
 * @property-read Doc $doc
 */
final class DocStatusHistory extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    public const UPDATED_AT = null;

    protected static string $ownerScopeConfigKey = 'docs.owner';

    public $timestamps = false;

    protected $fillable = [
        'doc_id',
        'status',
        'notes',
        'changed_by',
        'changed_by_type',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.doc_status_histories', 'doc_status_histories');
    }

    /**
     * @return BelongsTo<Doc, $this>
     */
    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocStatus::class,
            'changed_by_type' => 'string',
        ];
    }
}
