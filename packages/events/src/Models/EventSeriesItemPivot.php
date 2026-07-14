<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot representation of an event-series item.
 *
 * EventSeriesItem remains the package's aggregate model; this class provides
 * the BelongsToMany adapter for hosts that expose series as an attachable
 * relation.
 */
final class EventSeriesItemPivot extends Pivot
{
    use UsesEventUuid;

    public $incrementing = false;

    protected $fillable = [
        'id',
        'event_series_id',
        'seriesable_type',
        'seriesable_id',
        'event_id',
        'event_occurrence_id',
        'event_session_id',
        'title_override',
        'starts_at',
        'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_series_items', 'event_series_items');
    }

    public function setAttribute($key, $value): mixed
    {
        $result = parent::setAttribute($key, $value);

        $this->synchronizeSeriesableAttributes();

        return $result;
    }

    public function setRawAttributes(array $attributes, $sync = false): static
    {
        parent::setRawAttributes($attributes, $sync);
        $this->synchronizeSeriesableAttributes();

        return $this;
    }

    private function synchronizeSeriesableAttributes(): void
    {
        if (! filled($this->attributes['event_id'] ?? null)) {
            return;
        }

        $this->attributes['seriesable_type'] ??= Event::class;
        $this->attributes['seriesable_id'] ??= $this->attributes['event_id'];
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }
}
