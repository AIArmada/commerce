<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventSessionFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $timezone
 * @property string $status
 * @property string $visibility
 * @property string $delivery_mode
 * @property int|null $capacity
 * @property int $sort_order
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $delayed_at
 * @property CarbonImmutable|null $postponed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $archived_at
 * @property string|null $status_reason
 * @property string|null $status_message
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventLocation> $locations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventFacility> $facilities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventInvolvement> $involvements
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventMaterial> $materials
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventReference> $references
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventLink> $links
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventMedia> $media
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventLanguage> $languages
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventAudience> $audiences
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventEligibilityRule> $eligibilityRules
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventRegistration> $registrations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventRegistrationParticipant> $participants
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventAttendance> $attendances
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventChangeLog> $changeLogs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventUpdate> $updates
 */
final class EventSession extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id',
        'title', 'slug', 'summary', 'description',
        'starts_at', 'ends_at', 'timezone',
        'status', 'visibility', 'delivery_mode', 'capacity', 'sort_order',
        'published_at', 'delayed_at', 'postponed_at',
        'cancelled_at', 'completed_at', 'archived_at',
        'status_reason', 'status_message',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_sessions', 'event_sessions');
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'delayed_at' => 'immutable_datetime',
            'postponed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'capacity' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
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
     * @return HasMany<EventRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventRegistrationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(EventRegistrationParticipant::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(EventLocation::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventFacility, $this>
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(EventFacility::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventInvolvement, $this>
     */
    public function involvements(): HasMany
    {
        return $this->hasMany(EventInvolvement::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(EventMaterial::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventReference, $this>
     */
    public function references(): HasMany
    {
        return $this->hasMany(EventReference::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(EventLink::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(EventMedia::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventLanguage, $this>
     */
    public function languages(): HasMany
    {
        return $this->hasMany(EventLanguage::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventAudience, $this>
     */
    public function audiences(): HasMany
    {
        return $this->hasMany(EventAudience::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventEligibilityRule, $this>
     */
    public function eligibilityRules(): HasMany
    {
        return $this->hasMany(EventEligibilityRule::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventChangeLog, $this>
     */
    public function changeLogs(): HasMany
    {
        return $this->hasMany(EventChangeLog::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(EventUpdate::class, 'event_session_id');
    }

    protected static function newFactory(): EventSessionFactory
    {
        return EventSessionFactory::new();
    }

    public function speakerLineup(): HasMany
    {
        return $this->involvements();
    }
}
