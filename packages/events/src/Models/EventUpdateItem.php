<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventUpdateItemFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_update_id
 * @property string $field_key
 * @property mixed|null $old_value
 * @property mixed|null $new_value
 * @property mixed|null $old_value_json
 * @property mixed|null $new_value_json
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventUpdateItem extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_update_id',
        'field_key',
        'old_value', 'new_value',
        'old_value_json', 'new_value_json',
        'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_update_items', 'event_update_items');
    }

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'old_value_json' => 'array',
            'new_value_json' => 'array',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EventUpdate, $this>
     */
    public function eventUpdate(): BelongsTo
    {
        return $this->belongsTo(EventUpdate::class, 'event_update_id');
    }

    protected static function newFactory(): EventUpdateItemFactory
    {
        return EventUpdateItemFactory::new();
    }
}
