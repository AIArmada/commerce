<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Contacting\Concerns\HasContactMethods;
use AIArmada\Contacting\Concerns\HasSocialProfiles;
use AIArmada\Events\Database\Factories\EventFactory;
use AIArmada\Events\Enums\PricingMode;
use AIArmada\Events\Enums\RegistrationMode;
use AIArmada\Events\Enums\ScheduleKind;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use AIArmada\Events\States\EventStatus\EventStatus as EventStatusState;
use AIArmada\Events\States\EventStatus\Published;
use AIArmada\Events\Support\ModelResolver;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketType;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $created_by_type
 * @property string|null $created_by_id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property string $type
 * @property EventStatusState $status
 * @property string $visibility
 * @property ScheduleKind $schedule_kind
 * @property string $delivery_mode
 * @property string $timezone
 * @property string|null $default_venue_id
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $postponed_at
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $last_state_change_at
 * @property string|null $status_reason
 * @property string|null $status_message
 * @property string|null $pricing_mode
 * @property string|null $registration_mode
 * @property bool|null $issue_passes_for_free
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, EventOccurrence> $occurrences
 * @property-read Collection<int, EventSession> $sessions
 * @property-read Collection<int, EventLocation> $locations
 * @property-read Collection<int, EventFacility> $facilities
 * @property-read Collection<int, EventInvolvement> $involvements
 * @property-read Collection<int, EventAccessPolicy> $accessPolicies
 * @property-read Collection<int, EventRegistration> $registrations
 * @property-read Collection<int, TicketType> $ticketTypes
 * @property-read Collection<int, Pass> $passes
 * @property-read Collection<int, EventAttendance> $attendances
 * @property-read Collection<int, EventMaterial> $materials
 * @property-read Collection<int, EventReference> $references
 * @property-read Collection<int, EventLink> $links
 * @property-read Collection<int, EventMedia> $mediaRecords
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
 * @property-read Collection<int, EventEscalation> $escalations
 * @property-read Collection<int, SeatMap> $seatMaps
 * @property-read Model|Eloquent $owner
 * @property-read Model|Eloquent $createdBy
 */
class Event extends Model implements HasMedia
{
    use HasContactMethods;
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasSocialProfiles;
    use HasStates;
    use InteractsWithMedia;
    use UsesEventUuid;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    public const DRAFT = 'draft';

    public const PENDING_REVIEW = 'pending_review';

    public const SCHEDULED = 'scheduled';

    public const PUBLISHED = 'published';

    public const DELAYED = 'delayed';

    public const POSTPONED = 'postponed';

    public const RESCHEDULED = 'rescheduled';

    public const CANCELLED = 'cancelled';

    public const COMPLETED = 'completed';

    public const ARCHIVED = 'archived';

    public const VOIDED = 'voided';

    public const EXPIRED = 'expired';

    public const PUBLIC = 'public';

    public const UNLISTED = 'unlisted';

    public const PRIVATE = 'private';

    public const REGISTERED_ONLY = 'registered_only';

    public const ATTENDEES_ONLY = 'attendees_only';

    public const MANAGERS_ONLY = 'managers_only';

    public const INTERNAL = 'internal';

    public const DELIVERY_PHYSICAL = 'physical';

    public const DELIVERY_ONLINE = 'online';

    public const DELIVERY_HYBRID = 'hybrid';

    protected $fillable = [
        'owner_type', 'owner_id',
        'created_by_type', 'created_by_id',
        'title', 'slug', 'summary', 'description',
        'type', 'schedule_kind', 'status', 'visibility', 'delivery_mode',
        'timezone', 'default_venue_id',
        'pricing_mode', 'registration_mode', 'issue_passes_for_free',
        'published_at', 'cancelled_at', 'postponed_at', 'archived_at', 'completed_at',
        'status_reason', 'status_message',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.events', 'events');
    }

    protected function casts(): array
    {
        return [
            'schedule_kind' => ScheduleKind::class,
            'status' => EventStatusState::class,
            'pricing_mode' => PricingMode::class,
            'registration_mode' => RegistrationMode::class,
            'issue_passes_for_free' => 'boolean',
            'published_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'postponed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'last_state_change_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<EventOccurrence, $this>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(EventOccurrence::class);
    }

    /**
     * @return HasOne<EventOccurrence, $this>
     */
    public function primaryOccurrence(): HasOne
    {
        return $this->hasOne(EventOccurrence::class)
            ->orderBy('starts_at')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<EventSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class);
    }

    /**
     * @return HasMany<EventLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(EventLocation::class);
    }

    /**
     * @return HasOne<EventLocation, $this>
     */
    public function primaryLocation(): HasOne
    {
        return $this->hasOne(EventLocation::class)
            ->whereNull('event_occurrence_id')
            ->whereNull('event_session_id')
            ->where('location_role', 'primary')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<EventFacility, $this>
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(EventFacility::class);
    }

    /**
     * @return HasMany<EventInvolvement, $this>
     */
    public function involvements(): HasMany
    {
        return $this->hasMany(EventInvolvement::class);
    }

    /**
     * @return HasMany<EventAccessPolicy, $this>
     */
    public function accessPolicies(): HasMany
    {
        return $this->hasMany(EventAccessPolicy::class);
    }

    /**
     * @return HasMany<EventRegistration, $this>
     */
    public function registrations(): HasMany
    {
        /* @phpstan-ignore argument.templateType */
        return $this->hasMany(static::registrationModelClass(), 'event_id');
    }

    /**
     * @return MorphMany<TicketType, $this>
     */
    public function ticketTypes(): MorphMany
    {
        return $this->morphMany(TicketType::class, 'ticketable');
    }

    /**
     * @return MorphMany<Pass, $this>
     */
    public function passes(): MorphMany
    {
        return $this->morphMany(Pass::class, 'ticketable');
    }

    /**
     * @return HasMany<EventAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class);
    }

    /**
     * @return HasMany<EventMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(EventMaterial::class);
    }

    /**
     * @return HasMany<EventReference, $this>
     */
    public function referenceRecords(): HasMany
    {
        return $this->hasMany(EventReference::class);
    }

    /**
     * @return HasMany<EventLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(EventLink::class);
    }

    /**
     * @return HasMany<EventMedia, $this>
     */
    public function mediaRecords(): HasMany
    {
        return $this->hasMany(EventMedia::class);
    }

    /**
     * @return HasMany<EventLanguage, $this>
     */
    public function languages(): HasMany
    {
        return $this->hasMany(EventLanguage::class);
    }

    /**
     * @return HasMany<EventAudience, $this>
     */
    public function audiences(): HasMany
    {
        return $this->hasMany(EventAudience::class);
    }

    /**
     * @return HasMany<EventAudienceProfile, $this>
     */
    public function audienceProfiles(): HasMany
    {
        return $this->hasMany(EventAudienceProfile::class);
    }

    /**
     * @return HasMany<EventEligibilityRule, $this>
     */
    public function eligibilityRules(): HasMany
    {
        return $this->hasMany(EventEligibilityRule::class);
    }

    /**
     * @return HasMany<EventClassification, $this>
     */
    public function classifications(): HasMany
    {
        return $this->hasMany(EventClassification::class);
    }

    /**
     * @return HasMany<EventTimeExpression, $this>
     */
    public function timeExpressions(): HasMany
    {
        return $this->hasMany(EventTimeExpression::class);
    }

    /**
     * @return HasMany<EventItinerary, $this>
     */
    public function itineraries(): HasMany
    {
        return $this->hasMany(EventItinerary::class);
    }

    /**
     * @return HasMany<EventChangeLog, $this>
     */
    public function changeLogs(): HasMany
    {
        return $this->hasMany(EventChangeLog::class);
    }

    /**
     * @return HasMany<EventUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(EventUpdate::class);
    }

    /**
     * @return HasMany<EventNotificationBatch, $this>
     */
    public function notificationBatches(): HasMany
    {
        return $this->hasMany(EventNotificationBatch::class);
    }

    /**
     * @return MorphMany<SeatMap, $this>
     */
    public function seatMaps(): MorphMany
    {
        return $this->morphMany(SeatMap::class, 'seatable');
    }

    /**
     * @return HasMany<EventSubmission, $this>
     */
    public function submissions(): HasMany
    {
        /* @phpstan-ignore argument.templateType */
        return $this->hasMany(static::submissionModelClass(), 'event_id');
    }

    /**
     * @return HasOne<EventSubmission, $this>
     */
    public function originalSubmission(): HasOne
    {
        /* @phpstan-ignore argument.templateType */
        return $this->hasOne(static::submissionModelClass(), 'event_id')
            ->orderBy('submitted_at')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', self::PUBLIC);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFree(Builder $query): Builder
    {
        return $query->where('pricing_mode', PricingMode::Free->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeMixed(Builder $query): Builder
    {
        return $query->where('pricing_mode', PricingMode::Mixed->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpenDoor(Builder $query): Builder
    {
        return $query->where('registration_mode', RegistrationMode::None->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithResolvedModes(Builder $query): Builder
    {
        return $query->with(['ticketTypes:id,ticketable_id,ticketable_type,price']);
    }

    protected static function newFactory(): EventFactory
    {
        return EventFactory::new();
    }

    protected static function registrationModelClass(): string
    {
        return ModelResolver::registrationClass();
    }

    protected static function submissionModelClass(): string
    {
        return ModelResolver::submissionClass();
    }

    public function isPubliclyVisible(): bool
    {
        return $this->visibility === self::PUBLIC && $this->status instanceof Published;
    }

    public function effectivePricingMode(): PricingMode
    {
        if ($this->pricing_mode !== null) {
            return $this->pricing_mode instanceof PricingMode
                ? $this->pricing_mode
                : PricingMode::from($this->pricing_mode);
        }

        if (! config('events.features.free_only.auto_derive_pricing_from_ticket_types', true)) {
            return PricingMode::Paid;
        }

        /** @var Collection<int, TicketType> $ticketTypes */
        $ticketTypes = $this->relationLoaded('ticketTypes')
            ? $this->getRelation('ticketTypes')
            : $this->ticketTypes()->get(['id', 'ticketable_id', 'ticketable_type', 'price']);

        if ($ticketTypes->isEmpty()) {
            return PricingMode::Free;
        }

        $hasPaid = $ticketTypes->contains(fn ($t): bool => (float) $t->price > 0);
        $hasFree = $ticketTypes->contains(fn ($t): bool => (float) $t->price === 0.0);

        return match (true) {
            $hasPaid && $hasFree => PricingMode::Mixed,
            $hasPaid => PricingMode::Paid,
            default => PricingMode::Free,
        };
    }

    public function effectiveRegistrationMode(): RegistrationMode
    {
        if ($this->registration_mode !== null) {
            return $this->registration_mode instanceof RegistrationMode
                ? $this->registration_mode
                : RegistrationMode::from($this->registration_mode);
        }

        $default = config('events.features.free_only.default_registration_mode', 'required');

        return RegistrationMode::from($default);
    }

    public function isFree(): bool
    {
        return $this->effectivePricingMode()->isFreeOnly();
    }

    public function requiresRegistration(): bool
    {
        return $this->effectiveRegistrationMode()->isRequired();
    }

    public function isOpenDoor(): bool
    {
        return $this->effectiveRegistrationMode()->isOpenDoor();
    }

    public function shouldIssuePassesForFree(): bool
    {
        $default = (bool) config('events.features.free_only.auto_issue_passes_for_free', true);

        return $this->issue_passes_for_free !== null
            ? (bool) $this->issue_passes_for_free
            : $default;
    }

    public function shareTitle(): string
    {
        return $this->title;
    }

    public function shareUrl(): string
    {
        return URL::route(config('events.shares.route_name', 'events.show'), [$this->slug], true);
    }

    public function shareDescription(): ?string
    {
        return $this->summary;
    }

    public function shareImage(): ?string
    {
        $media = $this->mediaRecords->first();

        return $media?->url;
    }

    /**
     * @return HasMany<EventEscalation, $this>
     */
    public function escalations(): HasMany
    {
        return $this->hasMany(EventEscalation::class);
    }

    public function resolveEscalations(): void
    {
        $this->escalations()
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->withResponsiveImages()
            ->singleFile();

        $this->addMediaCollection('poster')
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->withResponsiveImages()
            ->singleFile();

        $this->addMediaCollection('gallery')
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->withResponsiveImages();
    }
}
