<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventAudienceProfileFactory;
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
 * @property bool $is_child_friendly
 * @property int|null $min_age
 * @property int|null $max_age
 * @property string|null $notes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventAudienceProfile extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'is_child_friendly', 'min_age', 'max_age',
        'notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_audience_profiles', 'event_audience_profiles');
    }

    protected function casts(): array
    {
        return [
            'is_child_friendly' => 'boolean',
            'min_age' => 'integer',
            'max_age' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    protected static function newFactory(): EventAudienceProfileFactory
    {
        return EventAudienceProfileFactory::new();
    }
}
