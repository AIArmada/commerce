<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Attendance\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Database\Factories\EventWalkInFactory;
use AIArmada\Events\Platform\ModelResolver;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
 * @property string|null $notes
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $event
 * @property-read Model|null $occurrence
 * @property-read Model|null $session
 * @property-read Model|Eloquent $recordedBy
 */
final class EventWalkIn extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'count', 'recorded_at',
        'recorded_by_type', 'recorded_by_id',
        'notes',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('events.database.tables.event_walk_ins', 'event_walk_ins');
    }

    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function event(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::eventClass();

        return $this->belongsTo($modelClass);
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function occurrence(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::occurrenceClass();

        return $this->belongsTo($modelClass, 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function session(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::sessionClass();

        return $this->belongsTo($modelClass, 'event_session_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function recordedBy(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): EventWalkInFactory
    {
        return EventWalkInFactory::new();
    }
}
