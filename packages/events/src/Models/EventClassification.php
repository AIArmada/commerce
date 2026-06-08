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
 * @property string|null $source_type
 * @property string|null $source_id
 * @property string $group_key
 * @property string $term_key
 * @property string|null $term_label
 * @property int|null $order_column
 * @property array<string, mixed>|null $metadata
 */
class EventClassification extends Model implements Auditable
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
        'source_type',
        'source_id',
        'group_key',
        'term_key',
        'term_label',
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
        return config('events.database.tables.classifications', 'event_classifications');
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
    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithGroupKey(Builder $query, array | string $groupKey): Builder
    {
        return $query->whereIn($this->qualifyColumn('group_key'), Arr::wrap($groupKey));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithTermKey(Builder $query, array | string $termKey): Builder
    {
        return $query->whereIn($this->qualifyColumn('term_key'), Arr::wrap($termKey));
    }
}
