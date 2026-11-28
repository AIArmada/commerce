<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Models;

use AIArmada\CommerceSupport\Concerns\ParsesPostgresTimestamps;
use AIArmada\Contacting\Features\ContactMethods\Concerns\HasContactMethods;
use AIArmada\Contacting\Features\SocialProfiles\Concerns\HasSocialProfiles;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Data\RegistrationMode;
use AIArmada\Events\Database\Factories\EventSessionFactory;
use AIArmada\Events\Features\Catalog\Actions\DeleteCatalogScopeDependents;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\OccurrenceStatus as OccurrenceStatusState;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\Events\Platform\RegistersEventMedia;
use AIArmada\Events\Platform\ScopesByEventOwner;
use AIArmada\Seating\Features\Maps\Models\SeatMap;
use AIArmada\Ticketing\Enums\PricingMode;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\ModelStates\HasStates;

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
 * @property OccurrenceStatusState $status
 * @property string $visibility
 * @property string $delivery_mode
 * @property int|null $capacity
 * @property int $sort_order
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $delayed_at
 * @property CarbonImmutable|null $postponed_at
 * @property CarbonImmutable|null $rescheduled_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $archived_at
 * @property string|null $pricing_mode
 * @property string|null $registration_mode
 * @property bool|null $issue_passes_for_free
 * @property string|null $status_reason
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read Collection<int, TicketType> $ticketTypes
 * @property-read Collection<int, Model> $locations
 * @property-read Collection<int, Model> $facilities
 * @property-read Collection<int, EventInvolvement> $involvements
 * @property-read Collection<int, EventAccessPolicy> $accessPolicies
 * @property-read Collection<int, EventMaterial> $materials
 * @property-read Collection<int, EventReference> $references
 * @property-read Collection<int, EventLink> $links
 * @property-read Collection<int, EventMedia> $mediaRecords
 * @property-read Collection<int, EventLanguage> $languages
 * @property-read Collection<int, EventAudience> $audiences
 * @property-read Collection<int, EventAudienceProfile> $audienceProfiles
 * @property-read Collection<int, Model> $classifications
 * @property-read Collection<int, EventTimeExpression> $timeExpressions
 * @property-read Collection<int, EventEligibilityRule> $eligibilityRules
 * @property-read Collection<int, Model> $registrations
 * @property-read Collection<int, Model> $registrationQuestions
 * @property-read Collection<int, Model> $participants
 * @property-read Collection<int, Model> $attendances
 * @property-read Collection<int, Pass> $passes
 * @property-read Collection<int, Model> $changeLogs
 * @property-read Collection<int, Model> $updates
 * @property-read Collection<int, SeatMap> $seatMaps
 */
final class EventSession extends Model implements HasMedia
{
    use HasContactMethods;
    use HasFactory;
    use HasSocialProfiles;
    use HasStates;
    use HasUuids;
    use InteractsWithMedia, RegistersEventMedia {
        RegistersEventMedia::registerMediaCollections insteadof InteractsWithMedia;
        RegistersEventMedia::registerMediaConversions insteadof InteractsWithMedia;
    }
    use ParsesPostgresTimestamps;
    use ScopesByEventOwner;

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
        'event_id', 'event_occurrence_id',
        'title', 'slug', 'summary', 'description',
        'starts_at', 'ends_at', 'timezone',
        'status', 'visibility', 'delivery_mode', 'capacity', 'sort_order',
        'pricing_mode', 'registration_mode', 'issue_passes_for_free',
        'published_at', 'delayed_at', 'postponed_at', 'rescheduled_at',
        'cancelled_at', 'completed_at', 'archived_at',
        'status_reason',
        'metadata',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('events.database.tables.event_sessions', 'event_sessions');
    }

    protected static function booted(): void
    {
        static::deleting(function (EventSession $session): void {
            app(DeleteCatalogScopeDependents::class)->deleteForScope('session', (string) $session->getKey(), $session->getMorphClass());
        });
    }

    protected function casts(): array
    {
        return [
            'status' => OccurrenceStatusState::class,
            'pricing_mode' => PricingMode::class,
            'registration_mode' => RegistrationMode::class,
            'issue_passes_for_free' => 'boolean',
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
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::eventClass());
    }

    /**
     * @return BelongsTo<EventOccurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    /**
     * @return MorphMany<TicketType, $this>
     */
    public function ticketTypes(): MorphMany
    {
        return $this->morphMany(TicketType::class, 'ticketable');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(ModelResolver::registrationClass(), 'event_session_id');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function registrationQuestions(): HasMany
    {
        return $this->hasMany(ModelResolver::registrationQuestionClass(), 'event_session_id')
            ->where('event_id', $this->event_id)
            ->where('event_occurrence_id', $this->event_occurrence_id)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ModelResolver::registrationParticipantClass(), 'event_session_id');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(ModelResolver::locationClass(), 'event_session_id');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(ModelResolver::facilityClass(), 'event_session_id');
    }

    /**
     * @return HasMany<EventInvolvement, $this>
     */
    public function involvements(): HasMany
    {
        return $this->hasMany(EventInvolvement::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventAccessPolicy, $this>
     */
    public function accessPolicies(): HasMany
    {
        return $this->hasMany(EventAccessPolicy::class, 'event_session_id');
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
    public function mediaRecords(): HasMany
    {
        return $this->hasMany(EventMedia::class, 'event_session_id');
    }

    protected function eventMediaProfile(): string
    {
        return 'session';
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
     * @return HasMany<EventAudienceProfile, $this>
     */
    public function audienceProfiles(): HasMany
    {
        return $this->hasMany(EventAudienceProfile::class, 'event_session_id');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function classifications(): HasMany
    {
        return $this->hasMany(ModelResolver::classificationClass(), 'event_session_id');
    }

    /**
     * @return HasMany<EventTimeExpression, $this>
     */
    public function timeExpressions(): HasMany
    {
        return $this->hasMany(EventTimeExpression::class, 'event_session_id');
    }

    /**
     * @return HasMany<EventEligibilityRule, $this>
     */
    public function eligibilityRules(): HasMany
    {
        return $this->hasMany(EventEligibilityRule::class, 'event_session_id');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(ModelResolver::attendanceClass(), 'event_session_id');
    }

    /**
     * @return MorphMany<Pass, $this>
     */
    public function passes(): MorphMany
    {
        return $this->morphMany(Pass::class, 'ticketable');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function changeLogs(): HasMany
    {
        return $this->hasMany(ModelResolver::changeLogClass(), 'event_session_id');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(ModelResolver::updateClass(), 'event_session_id');
    }

    /**
     * @return MorphMany<SeatMap, $this>
     */
    public function seatMaps(): MorphMany
    {
        return $this->morphMany(SeatMap::class, 'seatable');
    }

    public function delay(?string $reason = null, ?DateTimeInterface $expectedStartsAt = null): void
    {
        app(EventLifecycleWorkflow::class)->delay('session', (string) $this->getKey(), $reason, $expectedStartsAt);
    }

    public function postpone(?string $reason = null): void
    {
        app(EventLifecycleWorkflow::class)->postpone('session', (string) $this->getKey(), $reason);
    }

    public function cancel(?string $reason = null): void
    {
        app(EventLifecycleWorkflow::class)->cancel('session', (string) $this->getKey(), $reason);
    }

    public function reschedule(DateTimeInterface $startsAt, ?DateTimeInterface $endsAt = null, array $options = []): void
    {
        app(EventLifecycleWorkflow::class)->reschedule('session', (string) $this->getKey(), $startsAt, $endsAt, $options);
    }

    public function complete(): void
    {
        app(EventLifecycleWorkflow::class)->complete('session', (string) $this->getKey());
    }

    protected static function newFactory(): EventSessionFactory
    {
        return EventSessionFactory::new();
    }

    public function effectivePricingMode(): PricingMode
    {
        if ($this->pricing_mode !== null) {
            return $this->pricing_mode instanceof PricingMode
                ? $this->pricing_mode
                : PricingMode::from($this->pricing_mode);
        }

        if (! config('events.features.free_only.auto_derive_pricing_from_ticket_types', true)) {
            return $this->occurrence?->effectivePricingMode() ?? $this->event?->effectivePricingMode() ?? PricingMode::Paid;
        }

        /** @var Collection<int, TicketType> $ticketTypes */
        $ticketTypes = $this->relationLoaded('ticketTypes')
            ? $this->getRelation('ticketTypes')
            : $this->ticketTypes()->get(['id', 'ticketable_id', 'ticketable_type', 'price']);

        if ($ticketTypes->isEmpty()) {
            return $this->occurrence?->effectivePricingMode() ?? $this->event?->effectivePricingMode() ?? PricingMode::Paid;
        }

        $hasPaid = $ticketTypes->contains(fn ($t): bool => (int) $t->price > 0);
        $hasFree = $ticketTypes->contains(fn ($t): bool => (int) $t->price === 0);

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

        return $this->occurrence?->effectiveRegistrationMode() ?? $this->event?->effectiveRegistrationMode() ?? RegistrationMode::Required;
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
        if ($this->issue_passes_for_free !== null) {
            return (bool) $this->issue_passes_for_free;
        }

        return $this->occurrence?->shouldIssuePassesForFree() ?? $this->event?->shouldIssuePassesForFree() ?? true;
    }

    public function capacityRemaining(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        $blockingStatuses = config(
            'events.lifecycle.registration.capacity_blocking_statuses',
            ['pending', 'confirmed', 'refund_pending', 'checked_in'],
        );

        $reserved = $this->registrations()
            ->whereIn('status', $blockingStatuses)
            ->sum('total_participants');

        return max(0, $this->capacity - (int) $reserved);
    }

    /**
     * @param  Builder<EventSession>  $query
     * @return Builder<EventSession>
     */
    public function scopeWithResolvedModes(Builder $query): Builder
    {
        return $query->with([
            'ticketTypes:id,ticketable_id,ticketable_type,price',
            'occurrence.ticketTypes:id,ticketable_id,ticketable_type,price',
            'occurrence.event.ticketTypes:id,ticketable_id,ticketable_type,price',
            'event.ticketTypes:id,ticketable_id,ticketable_type,price',
        ]);
    }

    /**
     * @param  Builder<EventSession>  $query
     * @return Builder<EventSession>
     */
    public function scopeFree(Builder $query): Builder
    {
        return $query->where('pricing_mode', PricingMode::Free->value);
    }

    /**
     * @param  Builder<EventSession>  $query
     * @return Builder<EventSession>
     */
    public function scopeMixed(Builder $query): Builder
    {
        return $query->where('pricing_mode', PricingMode::Mixed->value);
    }

    /**
     * @param  Builder<EventSession>  $query
     * @return Builder<EventSession>
     */
    public function scopeOpenDoor(Builder $query): Builder
    {
        return $query->where('registration_mode', RegistrationMode::None->value);
    }

    public function speakerLineup(): HasMany
    {
        return $this->involvements();
    }
}
