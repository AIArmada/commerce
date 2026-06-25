<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
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
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $recorded_by_type
 * @property string|null $recorded_by_id
 * @property int $count
 * @property CarbonImmutable $recorded_at
 * @property string|null $interval_label
 * @property string|null $notes
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read EventSession|null $session
 * @property-read Model|Eloquent $recordedBy
 */
final class EventHeadcountLog extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesEventUuid;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'count', 'recorded_at', 'interval_label',
        'recorded_by_type', 'recorded_by_id',
        'notes',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_headcount_logs', 'event_headcount_logs');
    }

    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
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

    /**
     * @return MorphTo<Model, $this>
     */
    public function recordedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
