<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Changes\Models;

use AIArmada\Events\Database\Factories\EventUpdateFactory;
use AIArmada\Events\Features\Changes\Actions\DeleteUpdateDependents;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\Events\Platform\ScopesByEventOwner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $event_change_log_id
 * @property string|null $replacement_event_id
 * @property string|null $replacement_event_occurrence_id
 * @property string|null $replacement_event_session_id
 * @property string $update_type
 * @property string $title
 * @property string|null $message
 * @property string|null $notes
 * @property string $severity
 * @property string $visibility
 * @property bool $is_pinned
 * @property CarbonImmutable|null $starts_showing_at
 * @property CarbonImmutable|null $stops_showing_at
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $archived_at
 * @property string|null $created_by_type
 * @property string|null $created_by_id
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $event
 * @property-read Model|null $occurrence
 * @property-read Model|null $session
 * @property-read EventChangeLog|null $changeLog
 * @property-read Collection<int, EventUpdateItem> $items
 */
class EventUpdate extends Model
{
    use HasFactory;
    use HasUuids;
    use ScopesByEventOwner;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'event_change_log_id',
        'replacement_event_id', 'replacement_event_occurrence_id', 'replacement_event_session_id',
        'update_type', 'title', 'message',
        'notes',
        'severity', 'visibility',
        'is_pinned',
        'starts_showing_at', 'stops_showing_at',
        'published_at', 'archived_at',
        'created_by_type', 'created_by_id',
        'metadata',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('events.database.tables.event_updates', 'event_updates');
    }

    protected static function booted(): void
    {
        static::deleting(function (EventUpdate $update): void {
            app(DeleteUpdateDependents::class)->handle($update);
        });
    }

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'starts_showing_at' => 'immutable_datetime',
            'stops_showing_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::eventClass());
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::occurrenceClass(), 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::sessionClass(), 'event_session_id');
    }

    /**
     * @return BelongsTo<EventChangeLog, $this>
     */
    public function changeLog(): BelongsTo
    {
        return $this->belongsTo(EventChangeLog::class, 'event_change_log_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<EventUpdateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EventUpdateItem::class);
    }

    protected static function newFactory(): EventUpdateFactory
    {
        return EventUpdateFactory::new();
    }
}
