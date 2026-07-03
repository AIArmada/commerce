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
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use AIArmada\Events\States\EventStatus\EventStatus as EventStatusState;
use AIArmada\Events\States\EventStatus\Published;
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
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
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
 * @property string $delivery_mode
 * @property string $timezone
 * @property string|null $default_venue_id
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $postponed_at
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable|null $completed_at
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
 * @property-read Collection<int, SeatMap> $seatMaps
 * @property-read Model|Eloquent $owner
 * @property-read Model|Eloquent $createdBy
 */
final class Event extends Model
{
    use HasContactMethods;
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasSocialProfiles;
    use HasStates;
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
        'type', 'status', 'visibility', 'delivery_mode',
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
            'status' => EventStatusState::class,
            'pricing_mode' => PricingMode::class,
            'registration_mode' => RegistrationMode::class,
            'issue_passes_for_free' => 'boolean',
            'published_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'postponed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
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
        return $this->hasMany(EventRegistration::class);
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
    public function references(): HasMany
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
    public function media(): HasMany
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
        $media = $this->media->first();

        return $media?->url;
    }

    /**
     * Read a display-oriented key from this Event.
     *
     * Maps config-style dotted paths to model columns or metadata:
     *   series.name / event.name        → $this->title
     *   event.summary                   → $this->summary
     *   event.description               → $this->description
     *   event.default_timezone          → $this->timezone
     *   event.time_label                → $this->metadata['time_label']
     *   venue.short_name                → $this->metadata['venue']['short_name']
     *   occurrences                     → $this->metadata['occurrences']
     */
    public function metadata(string $key, mixed $default = null): mixed
    {
        return match (true) {
            $key === 'series.name', $key === 'event.name' => $this->title,
            $key === 'series.slug', $key === 'event.slug' => $this->slug,
            $key === 'event.summary', $key === 'series.description' => $this->summary ?? $default,
            $key === 'event.description' => $this->description ?? $default,
            $key === 'event.default_timezone' => $this->timezone ?? $default,
            str_starts_with($key, 'event.') => Arr::get($this->metadata ?? [], mb_substr($key, 6), $default),
            default => Arr::get($this->metadata ?? [], $key, $default),
        };
    }

    /**
     * Static shortcut for metadata() on the first event.
     */
    public static function metadataValue(string $key, mixed $default = null, ?string $slug = null): mixed
    {
        $query = self::query();

        if ($slug !== null) {
            $query->where('slug', $slug);
        }

        $event = $query->first();

        return $event?->metadata($key, $default) ?? $default;
    }

    /**
     * First occurrence date label, read from metadata.
     */
    public static function occurrenceLabel(string $preferredDate, ?string $slug = null): ?string
    {
        $data = self::metadataValue("occurrences.{$preferredDate}", null, $slug);

        return is_array($data) && isset($data['label']) && is_string($data['label'])
            ? $data['label']
            : null;
    }

    /**
     * First occurrence start time, read from metadata.
     */
    public static function occurrenceStartsAt(string $preferredDate, ?string $slug = null): ?string
    {
        $data = self::metadataValue("occurrences.{$preferredDate}", null, $slug);

        return is_array($data) && isset($data['starts_at']) && is_string($data['starts_at'])
            ? $data['starts_at']
            : null;
    }

    /**
     * @return array<int, string>
     */
    public static function preferredDates(?string $slug = null): array
    {
        $occurrences = self::metadataValue('occurrences', [], $slug);

        if (! is_array($occurrences)) {
            return [];
        }

        return array_values(array_filter(
            array_keys($occurrences),
            static fn (mixed $date): bool => is_string($date) && $date !== '',
        ));
    }
}
