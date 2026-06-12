<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventModerationActionFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $event_report_id
 * @property string $actionable_type
 * @property string $actionable_id
 * @property string|null $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string $action_type
 * @property string $status
 * @property string|null $reason
 * @property string|null $notes
 * @property string|null $performed_by_type
 * @property string|null $performed_by_id
 * @property CarbonImmutable|null $performed_at
 * @property CarbonImmutable|null $reversed_at
 * @property CarbonImmutable|null $expired_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $actionable
 */
final class EventModerationAction extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_report_id',
        'actionable_type', 'actionable_id',
        'event_id', 'event_occurrence_id', 'event_session_id',
        'action_type', 'status',
        'reason', 'notes',
        'performed_by_type', 'performed_by_id',
        'performed_at', 'reversed_at', 'expired_at',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_moderation_actions', 'event_moderation_actions');
    }

    protected function casts(): array
    {
        return [
            'performed_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * @return BelongsTo<EventOccurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<EventSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    protected static function newFactory(): EventModerationActionFactory
    {
        return EventModerationActionFactory::new();
    }
}
