<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventMaterialFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $material_type
 * @property string|null $material_id
 * @property string|null $usage_type
 * @property string $title
 * @property string|null $url
 * @property string $visibility
 * @property int $sort_order
 * @property string|null $notes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventMaterial extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'material_type', 'material_id', 'usage_type',
        'title', 'url',
        'visibility', 'sort_order', 'notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_materials', 'event_materials');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    protected static function newFactory(): EventMaterialFactory
    {
        return EventMaterialFactory::new();
    }
}
