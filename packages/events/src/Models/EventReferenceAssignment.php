<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $assignable_type
 * @property string|null $assignable_id
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string $reference_kind
 * @property string|null $display_label
 * @property string|null $source_label
 * @property string|null $url
 * @property int|null $order_column
 * @property array<string, mixed>|null $metadata
 */
class EventReferenceAssignment extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'assignable_type',
        'assignable_id',
        'reference_type',
        'reference_id',
        'reference_kind',
        'display_label',
        'source_label',
        'url',
        'order_column',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'order_column' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('events.database.tables.references', 'event_reference_assignments');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function assignable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'assignable_type', 'assignable_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    /**
     * @param  Builder<static>  $query
     * @param  array<int, string>|string  $referenceKinds
     * @return Builder<static>
     */
    public function scopeWithReferenceKind(Builder $query, array | string $referenceKinds): Builder
    {
        return $query->whereIn($this->qualifyColumn('reference_kind'), Arr::wrap($referenceKinds));
    }

    /**
     * @param  Builder<static>  $query
     * @param  array<int, string>|string  $sourceLabels
     * @return Builder<static>
     */
    public function scopeWithSourceLabel(Builder $query, array | string $sourceLabels): Builder
    {
        return $query->whereIn($this->qualifyColumn('source_label'), Arr::wrap($sourceLabels));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithReferenceType(Builder $query, array | string $referenceTypes): Builder
    {
        return $query->whereIn($this->qualifyColumn('reference_type'), Arr::wrap($referenceTypes));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithReferenceId(Builder $query, array | string $referenceIds): Builder
    {
        return $query->whereIn($this->qualifyColumn('reference_id'), Arr::wrap($referenceIds));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithDisplayLabel(Builder $query, array | string $displayLabels): Builder
    {
        return $query->whereIn($this->qualifyColumn('display_label'), Arr::wrap($displayLabels));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithUrl(Builder $query, array | string $urls): Builder
    {
        return $query->whereIn($this->qualifyColumn('url'), Arr::wrap($urls));
    }
}
