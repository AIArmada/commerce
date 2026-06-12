<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventReferenceFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $referenceable_type
 * @property string|null $referenceable_id
 * @property string $reference_type
 * @property string $title
 * @property string|null $url
 * @property string|null $citation
 * @property string $visibility
 * @property int $sort_order
 * @property string|null $notes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventReference extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'referenceable_type', 'referenceable_id',
        'reference_type',
        'title', 'url', 'citation',
        'visibility', 'sort_order', 'notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_references', 'event_references');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): EventReferenceFactory
    {
        return EventReferenceFactory::new();
    }
}
