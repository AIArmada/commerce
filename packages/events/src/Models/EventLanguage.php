<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventLanguageFactory;
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
 * @property string $language_code
 * @property string $usage_type
 * @property bool $is_primary
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventLanguage extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'language_code', 'usage_type',
        'is_primary', 'sort_order',
        'metadata',
    ];



    protected static function booted(): void
    {
        static::saving(function ($model) {
            if (! isset($model->attributes['event_id'])) {
                echo 'DEBUG: EventLanguage saving WITHOUT event_id in attributes' . PHP_EOL;
                echo '  attributes keys: ' . implode(', ', array_keys($model->attributes)) . PHP_EOL;
                debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            }
        });
    }
    public function getTable(): string
    {
        return config('events.database.tables.event_languages', 'event_languages');
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Event, $this> */


    public function getEventIdAttribute()
    {
        echo 'DEBUG: EventLanguage->event_id accessed' . PHP_EOL;
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        return $this->attributes['event_id'] ?? null;
    }
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    protected static function newFactory(): EventLanguageFactory
    {
        return EventLanguageFactory::new();
    }
}
