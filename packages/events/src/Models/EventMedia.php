<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventMediaFactory;
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
 * @property string $media_type
 * @property string|null $usage_type
 * @property string|null $file_id
 * @property string|null $url
 * @property string|null $title
 * @property string|null $caption
 * @property string|null $alt_text
 * @property string $visibility
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventMedia extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'media_type', 'usage_type',
        'file_id', 'url',
        'title', 'caption', 'alt_text',
        'visibility', 'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_media', 'event_media');
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

    protected static function newFactory(): EventMediaFactory
    {
        return EventMediaFactory::new();
    }
}
