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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $occurrence_id
 * @property string $segment_key
 * @property string|null $segment_type
 * @property string|null $title
 * @property string|null $description
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $duration_minutes
 * @property int|null $order_column
 * @property array<string, mixed>|null $metadata
 */
class EventAgendaItem extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'occurrence_id',
        'segment_key',
        'segment_type',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'order_column',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'duration_minutes' => 'integer',
            'order_column' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('events.database.tables.agenda_items', 'event_agenda_items');
    }

    /**
     * @return BelongsTo<Occurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class, 'occurrence_id');
    }

    public function isTimed(): bool
    {
        return $this->starts_at !== null || $this->ends_at !== null || $this->duration_minutes !== null;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithSegmentKey(Builder $query, array | string $segmentKeys): Builder
    {
        return $query->whereIn($this->qualifyColumn('segment_key'), Arr::wrap($segmentKeys));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithSegmentType(Builder $query, array | string $segmentTypes): Builder
    {
        return $query->whereIn($this->qualifyColumn('segment_type'), Arr::wrap($segmentTypes));
    }
}
