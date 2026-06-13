<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Contacting\Concerns\HasContactMethods;
use AIArmada\Contacting\Concerns\HasSocialProfiles;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Database\Factories\EventOccurrenceFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string $title
 * @property string $slug
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $timezone
 * @property string $status
 * @property string $visibility
 * @property string $delivery_mode
 * @property int|null $capacity
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $delayed_at
 * @property CarbonImmutable|null $postponed_at
 * @property CarbonImmutable|null $rescheduled_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $archived_at
 * @property string|null $rescheduled_from_occurrence_id
 * @property string|null $rescheduled_to_occurrence_id
 * @property string|null $status_reason
 * @property string|null $status_message
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read Collection<int, EventSession> $sessions
 * @property-read Collection<int, EventLocation> $locations
 * @property-read Collection<int, EventFacility> $facilities
 * @property-read Collection<int, EventInvolvement> $involvements
 * @property-read Collection<int, EventAccessPolicy> $accessPolicies
 * @property-read Collection<int, EventRegistration> $registrations
 * @property-read Collection<int, EventRegistrationParticipant> $participants
 * @property-read Collection<int, EventTicketType> $ticketTypes
 * @property-read Collection<int, EventPass> $passes
 * @property-read Collection<int, EventAttendance> $attendances
 * @property-read Collection<int, EventMaterial> $materials
 * @property-read Collection<int, EventReference> $references
 * @property-read Collection<int, EventLink> $links
 * @property-read Collection<int, EventMedia> $media
 * @property-read Collection<int, EventLanguage> $languages
 * @property-read Collection<int, EventAudience> $audiences
 * @property-read Collection<int, EventAudienceProfile> $audienceProfiles
 * @property-read Collection<int, EventEligibilityRule> $eligibilityRules
 * @property-read Collection<int, EventClassification> $classifications
 * @property-read Collection<int, EventTimeExpression> $timeExpressions
 * @property-read Collection<int, EventItinerary> $itineraries
 * @property-read Collection<int, EventChangeLog> $changeLogs
 * @property-read Collection<int, EventUpdate> $updates
 * @property-read Collection<int, EventNotificationBatch> $notificationBatches
 * @property-read EventOccurrence|null $rescheduledFromOccurrence
 * @property-read EventOccurrence|null $rescheduledToOccurrence
 */
final class EventOccurrence extends Model
{
    use HasContactMethods;
    use HasFactory;
    use HasSocialProfiles;
    use UsesEventUuid;

    public const DRAFT = 'draft';

    public const SCHEDULED = 'scheduled';

    public const PUBLISHED = 'published';

    public const DELAYED = 'delayed';

    public const POSTPONED = 'postponed';

    public const RESCHEDULED = 'rescheduled';

    public const CANCELLED = 'cancelled';

    public const COMPLETED = 'completed';

    public const ARCHIVED = 'archived';

    protected $fillable = [
        'event_id',
        'title', 'slug',
        'starts_at', 'ends_at', 'timezone',
        'status', 'visibility', 'delivery_mode', 'capacity',
        'published_at', 'delayed_at', 'postponed_at', 'rescheduled_at',
        'cancelled_at', 'completed_at', 'archived_at',
        'rescheduled_from_occurrence_id', 'rescheduled_to_occurrence_id',
        'status_reason', 'status_message',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_occurrences', 'event_occurrences');
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'delayed_at' => 'immutable_datetime',
            'postponed_at' => 'immutable_datetime',
            'rescheduled_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'capacity' => 'integer',
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
     * @return HasMany<EventSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(EventLocation::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventFacility, $this>
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(EventFacility::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventInvolvement, $this>
     */
    public function involvements(): HasMany
    {
        return $this->hasMany(EventInvolvement::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventAccessPolicy, $this>
     */
    public function accessPolicies(): HasMany
    {
        return $this->hasMany(EventAccessPolicy::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventRegistrationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(EventRegistrationParticipant::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventTicketType, $this>
     */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(EventTicketType::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventPass, $this>
     */
    public function passes(): HasMany
    {
        return $this->hasMany(EventPass::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(EventMaterial::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventReference, $this>
     */
    public function references(): HasMany
    {
        return $this->hasMany(EventReference::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(EventLink::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(EventMedia::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventLanguage, $this>
     */
    public function languages(): HasMany
    {
        return $this->hasMany(EventLanguage::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventAudience, $this>
     */
    public function audiences(): HasMany
    {
        return $this->hasMany(EventAudience::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventAudienceProfile, $this>
     */
    public function audienceProfiles(): HasMany
    {
        return $this->hasMany(EventAudienceProfile::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventEligibilityRule, $this>
     */
    public function eligibilityRules(): HasMany
    {
        return $this->hasMany(EventEligibilityRule::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventClassification, $this>
     */
    public function classifications(): HasMany
    {
        return $this->hasMany(EventClassification::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventTimeExpression, $this>
     */
    public function timeExpressions(): HasMany
    {
        return $this->hasMany(EventTimeExpression::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventItinerary, $this>
     */
    public function itineraries(): HasMany
    {
        return $this->hasMany(EventItinerary::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventChangeLog, $this>
     */
    public function changeLogs(): HasMany
    {
        return $this->hasMany(EventChangeLog::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(EventUpdate::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventNotificationBatch, $this>
     */
    public function notificationBatches(): HasMany
    {
        return $this->hasMany(EventNotificationBatch::class, 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<EventOccurrence, $this>
     */
    public function rescheduledFromOccurrence(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_occurrence_id');
    }

    /**
     * @return BelongsTo<EventOccurrence, $this>
     */
    public function rescheduledToOccurrence(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_to_occurrence_id');
    }

    public function delay(?string $reason = null, ?DateTimeInterface $expectedStartsAt = null): void
    {
        app(EventLifecycleWorkflow::class)->delay($this, $reason, $expectedStartsAt);
    }

    public function postpone(?string $reason = null): void
    {
        app(EventLifecycleWorkflow::class)->postpone($this, $reason);
    }

    public function cancel(?string $reason = null): void
    {
        app(EventLifecycleWorkflow::class)->cancel($this, $reason);
    }

    protected static function newFactory(): EventOccurrenceFactory
    {
        return EventOccurrenceFactory::new();
    }

    public function complete(): void
    {
        app(EventLifecycleWorkflow::class)->complete($this);
    }

    public function capacityRemaining(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        $blockingStatuses = config(
            'events.lifecycle.registration.capacity_blocking_statuses',
            EventRegistration::CAPACITY_BLOCKING_STATUSES,
        );

        $reserved = $this->registrations()
            ->whereIn('status', $blockingStatuses)
            ->sum('total_participants');

        return max(0, $this->capacity - (int) $reserved);
    }
}
