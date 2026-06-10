<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Contracts\EventDisplayTimezoneResolver;
use AIArmada\Events\Contracts\EventRelationalContentSubject;
use AIArmada\Events\Data\EventAddressData;
use AIArmada\Events\Enums\EventFormat;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Enums\OccurrenceParticipationMode;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Events\OccurrenceCancelled;
use AIArmada\Events\Events\OccurrenceCompleted;
use AIArmada\Events\Events\OccurrenceDelayed;
use AIArmada\Events\Events\OccurrenceLive;
use AIArmada\Events\Events\OccurrencePostponed;
use AIArmada\Events\Events\OccurrenceScheduled;
use AIArmada\Events\Exceptions\InvalidEventStatusTransition;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\Events\Support\Integration\ConfiguredEventModel;
use AIArmada\Events\Support\Integration\EventAddressResolver;
use AIArmada\Events\Support\Policy\LifecyclePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $event_id
 * @property string|null $address_type
 * @property string|null $address_id
 * @property string|null $sub_location_id
 * @property string|null $product_id
 * @property string|null $variant_id
 * @property string|null $name
 * @property OccurrenceStatus $status
 * @property OccurrenceParticipationMode $participation_mode
 * @property int|null $capacity
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property string $timezone
 * @property CarbonImmutable|null $registration_opens_at
 * @property CarbonImmutable|null $registration_closes_at
 * @property CarbonImmutable|null $check_in_opens_at
 * @property CarbonImmutable|null $check_in_closes_at
 * @property string|null $schedule_mode
 * @property string|null $schedule_reference_key
 * @property array<string, mixed>|null $schedule_reference_payload
 * @property string|null $schedule_label
 * @property string $registration_mode
 * @property string $duplicate_strategy
 * @property bool $waitlist_enabled
 * @property bool $approval_required
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $live_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $postponed_at
 * @property CarbonImmutable|null $delayed_at
 * @property CarbonImmutable|null $visible_at
 * @property CarbonImmutable|null $cancelled_at
 * @property array<string, mixed>|null $metadata
 * @property-read string|null $location_label
 * @property-read string|null $address_label
 * @property-read array<int, string> $address_lines
 * @property-read string|null $address_country
 * @property-read string|null $address_timezone
 * @property-read string|null $address_latitude
 * @property-read string|null $address_longitude
 */
class Occurrence extends Model implements Auditable, EventRelationalContentSubject
{
    use HasCommerceAudit;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'event_id',
        'address_type',
        'address_id',
        'sub_location_id',
        'product_id',
        'variant_id',
        'name',
        'status',
        'visibility',
        'format',
        'participation_mode',
        'capacity',
        'starts_at',
        'ends_at',
        'timezone',
        'registration_opens_at',
        'registration_closes_at',
        'check_in_opens_at',
        'check_in_closes_at',
        'metadata',
        'schedule_mode',
        'schedule_reference_key',
        'schedule_reference_payload',
        'schedule_label',
        'registration_mode',
        'duplicate_strategy',
        'waitlist_enabled',
        'approval_required',
        'scheduled_at',
        'live_at',
        'completed_at',
        'postponed_at',
        'delayed_at',
        'visible_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OccurrenceStatus::class,
            'visibility' => EventVisibility::class,
            'format' => EventFormat::class,
            'capacity' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'registration_opens_at' => 'immutable_datetime',
            'registration_closes_at' => 'immutable_datetime',
            'check_in_opens_at' => 'immutable_datetime',
            'check_in_closes_at' => 'immutable_datetime',
            'scheduled_at' => 'immutable_datetime',
            'live_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'postponed_at' => 'immutable_datetime',
            'delayed_at' => 'immutable_datetime',
            'visible_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'metadata' => 'array',
            'schedule_reference_payload' => 'array',
            'waitlist_enabled' => 'boolean',
            'approval_required' => 'boolean',
        ];
    }

    protected $attributes = [
        'status' => 'draft',
        'visibility' => 'public',
        'participation_mode' => 'registration_required',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $occurrence): void {
            if (! $occurrence->isDirty('status')) {
                return;
            }

            $current = $occurrence->getOriginal('status');
            $next = $occurrence->status;

            if (! $next instanceof OccurrenceStatus) {
                return;
            }

            $currentStatus = $current instanceof OccurrenceStatus
                ? $current
                : (is_string($current) ? OccurrenceStatus::tryFrom($current) : null);

            if ($currentStatus !== null && $currentStatus === $next) {
                return;
            }

            if ($currentStatus !== null && ! $currentStatus->canTransitionTo($next)) {
                throw InvalidEventStatusTransition::from($currentStatus, $next);
            }

            $now = now();

            if ($next === OccurrenceStatus::Scheduled && $occurrence->scheduled_at === null) {
                $occurrence->scheduled_at = $now->toImmutable();
            }

            if ($next === OccurrenceStatus::Live && $occurrence->live_at === null) {
                $occurrence->live_at = $now->toImmutable();
            }

            if ($next === OccurrenceStatus::Completed && $occurrence->completed_at === null) {
                $occurrence->completed_at = $now->toImmutable();
            }

            if ($next === OccurrenceStatus::Postponed && $occurrence->postponed_at === null) {
                $occurrence->postponed_at = $now->toImmutable();
            }

            if ($next === OccurrenceStatus::Delayed && $occurrence->delayed_at === null) {
                $occurrence->delayed_at = $now->toImmutable();
            }

            if ($next === OccurrenceStatus::Cancelled && $occurrence->cancelled_at === null) {
                $occurrence->cancelled_at = $now->toImmutable();
            }
        });

        static::saved(function (self $occurrence): void {
            if (! $occurrence->wasChanged('status')) {
                return;
            }

            $next = $occurrence->status;

            match (true) {
                $next === OccurrenceStatus::Scheduled => event(new OccurrenceScheduled($occurrence)),
                $next === OccurrenceStatus::Live => event(new OccurrenceLive($occurrence)),
                $next === OccurrenceStatus::Completed => event(new OccurrenceCompleted($occurrence)),
                $next === OccurrenceStatus::Postponed => event(new OccurrencePostponed($occurrence)),
                $next === OccurrenceStatus::Delayed => event(new OccurrenceDelayed($occurrence)),
                $next === OccurrenceStatus::Cancelled => event(new OccurrenceCancelled($occurrence)),
                default => null,
            };
        });

        static::creating(function (self $occurrence): void {
            $participationMode = $occurrence->getAttribute('participation_mode');

            if ($participationMode instanceof OccurrenceParticipationMode) {
                return;
            }

            if (is_string($participationMode) && mb_trim($participationMode) !== '') {
                return;
            }

            $configuredMode = config('events.defaults.occurrence_participation_mode', OccurrenceParticipationMode::RegistrationRequired->value);
            $occurrence->setAttribute(
                'participation_mode',
                is_string($configuredMode) && OccurrenceParticipationMode::tryFrom($configuredMode) instanceof OccurrenceParticipationMode
                    ? $configuredMode
                    : OccurrenceParticipationMode::RegistrationRequired->value,
            );
        });
    }

    public function getTable(): string
    {
        return config('events.database.tables.occurrences', 'event_occurrences');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?Model $owner = null, bool $includeGlobal = false): Builder
    {
        $ownerToScope = $owner;

        if (func_num_args() < 2) {
            $ownerToScope = OwnerContext::CURRENT;
        }

        $includeGlobalToScope = $includeGlobal;

        if (func_num_args() < 3) {
            $includeGlobalToScope = (bool) config('events.features.owner.include_global', false);
        }

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(
            ConfiguredEventModel::classFor('events.models.event', Event::class),
            'event_id',
        );
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePubliclyAccessible(Builder $query, ?CarbonImmutable $now = null): Builder
    {
        $now ??= CarbonImmutable::now('UTC');

        return $query
            ->whereIn($this->qualifyColumn('visibility'), [
                EventVisibility::Public->value,
                EventVisibility::Unlisted->value,
            ])
            ->where(function (Builder $query) use ($now): void {
                $query
                    ->whereNull($this->qualifyColumn('visible_at'))
                    ->orWhere($this->qualifyColumn('visible_at'), '<=', $now);
            });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePubliclyDiscoverable(Builder $query, ?CarbonImmutable $now = null): Builder
    {
        $now ??= CarbonImmutable::now('UTC');

        return $query
            ->where($this->qualifyColumn('visibility'), EventVisibility::Public->value)
            ->where(function (Builder $query) use ($now): void {
                $query
                    ->whereNull($this->qualifyColumn('visible_at'))
                    ->orWhere($this->qualifyColumn('visible_at'), '<=', $now);
            });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function address(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'address_type', 'address_id');
    }

    /**
     * @return BelongsTo<EventSubLocation, $this>
     *
     * @phpstan-return BelongsTo<EventSubLocation, $this>
     */
    public function subLocation(): BelongsTo
    {
        return $this->belongsTo(
            ConfiguredEventModel::classFor('events.models.sub_location', EventSubLocation::class),
            'sub_location_id',
        );
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(
            CommerceIntegration::requireModelClass('product_model', 'products'),
            'product_id',
        );
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            CommerceIntegration::requireModelClass('variant_model', 'products'),
            'variant_id',
        );
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'occurrence_id');
    }

    /**
     * @return MorphMany<EventClassification, $this>
     */
    public function classifications(): MorphMany
    {
        return $this->morphMany(EventClassification::class, 'assignable');
    }

    /**
     * @return MorphMany<EventAsset, $this>
     */
    public function assets(): MorphMany
    {
        return $this->morphMany(EventAsset::class, 'assignable');
    }

    /**
     * @return MorphMany<EventReference, $this>
     */
    public function references(): MorphMany
    {
        return $this->morphMany(EventReference::class, 'assignable')
            ->orderBy((new EventReference)->qualifyColumn('reference_kind'))
            ->orderBy((new EventReference)->qualifyColumn('order_column'));
    }

    /**
     * @return HasMany<EventAgenda, $this>
     */
    public function agendaItems(): HasMany
    {
        return $this->hasMany(EventAgenda::class, 'occurrence_id')
            ->orderBy((new EventAgenda)->qualifyColumn('order_column'))
            ->orderBy((new EventAgenda)->qualifyColumn('starts_at'))
            ->orderBy((new EventAgenda)->qualifyColumn('segment_key'));
    }

    /**
     * @return HasMany<EventChange, $this>
     */
    public function changeNotices(): HasMany
    {
        return $this->hasMany(EventChange::class, 'replacement_occurrence_id');
    }

    /**
     * @return HasMany<EventAttendance, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(EventAttendance::class, 'occurrence_id');
    }

    /**
     * @return HasMany<EventEngagement, $this>
     */
    public function engagements(): HasMany
    {
        return $this->hasMany(EventEngagement::class, 'occurrence_id');
    }

    /**
     * @return MorphMany<EventPerson, $this>
     */
    public function people(): MorphMany
    {
        return $this->morphMany(EventPerson::class, 'assignable');
    }

    /**
     * @return MorphMany<EventSubmission, $this>
     */
    public function submissions(): MorphMany
    {
        return $this->morphMany(EventSubmission::class, 'assignable');
    }

    /**
     * @return MorphMany<EventSeatCategory, $this>
     */
    public function seatCategories(): MorphMany
    {
        return $this->morphMany(EventSeatCategory::class, 'assignable');
    }

    /**
     * @return HasMany<EventRegistrationGroup, $this>
     */
    public function registrationGroups(): HasMany
    {
        return $this->hasMany(EventRegistrationGroup::class, 'occurrence_id');
    }

    /**
     * @return array<int, mixed>|array<string, mixed>
     */
    public function referenceMaterials(?string $kind = null): array
    {
        $references = $this->relationLoaded('references')
            ? $this->references
            : $this->references()->orderBy('reference_kind')->orderBy('order_column')->get();

        if ($references->isEmpty()) {
            return $kind === null ? [] : [];
        }

        $grouped = $references
            ->groupBy('reference_kind')
            ->map(static function (Collection $items): array {
                return $items
                    ->sortBy('order_column')
                    ->map(static fn (EventReference $reference): array => [
                        'reference_kind' => $reference->reference_kind,
                        'reference_type' => $reference->reference_type,
                        'reference_id' => $reference->reference_id,
                        'display_label' => $reference->display_label,
                        'source_label' => $reference->source_label,
                        'url' => $reference->url,
                        'order_column' => $reference->order_column,
                        'metadata' => $reference->metadata,
                    ])
                    ->values()
                    ->all();
            })
            ->all();

        if ($kind === null) {
            return $grouped;
        }

        return $grouped[$kind] ?? [];
    }

    public function acceptsRegistrations(): bool
    {
        if (! $this->resolvedParticipationMode()->acceptsRegistrations()) {
            return false;
        }

        if (! LifecyclePolicy::occurrenceAcceptsRegistrations($this->status)) {
            return false;
        }

        $now = now('UTC');

        if ($this->registration_opens_at !== null && $this->registration_opens_at->gt($now)) {
            return false;
        }

        if ($this->registration_closes_at !== null && $this->registration_closes_at->lt($now)) {
            return false;
        }

        return true;
    }

    public function acceptsCheckIn(): bool
    {
        if (! $this->resolvedParticipationMode()->acceptsRegistrations()) {
            return false;
        }

        if (! LifecyclePolicy::occurrenceAcceptsCheckIn($this->status)) {
            return false;
        }

        $now = now('UTC');

        if ($this->check_in_opens_at !== null && $this->check_in_opens_at->gt($now)) {
            return false;
        }

        if ($this->check_in_closes_at !== null && $this->check_in_closes_at->lt($now)) {
            return false;
        }

        return true;
    }

    public function acceptsWalkIns(): bool
    {
        if (! $this->resolvedParticipationMode()->acceptsWalkIns()) {
            return false;
        }

        if (! LifecyclePolicy::occurrenceAcceptsWalkIns($this->status)) {
            return false;
        }

        $now = now('UTC');

        if ($this->check_in_opens_at !== null && $this->check_in_opens_at->gt($now)) {
            return false;
        }

        if ($this->check_in_closes_at !== null && $this->check_in_closes_at->lt($now)) {
            return false;
        }

        return true;
    }

    public function usesScheduleReference(): bool
    {
        return $this->schedule_mode !== null
            || $this->schedule_reference_key !== null
            || $this->schedule_reference_payload !== null;
    }

    public function addressData(): ?EventAddressData
    {
        return app(EventAddressResolver::class)->data($this->address);
    }

    public function addressLabel(): ?string
    {
        return $this->addressData()?->label;
    }

    public function locationLabel(): ?string
    {
        return app(EventAddressResolver::class)->label($this->address, $this->subLocation);
    }

    /**
     * @return array<int, string>
     */
    public function addressLines(): array
    {
        return app(EventAddressResolver::class)->lines($this->address);
    }

    public function addressCountry(): ?string
    {
        return app(EventAddressResolver::class)->country($this->address);
    }

    public function addressTimezone(): ?string
    {
        return app(EventAddressResolver::class)->timezone($this->address);
    }

    public function addressLatitude(): ?string
    {
        return app(EventAddressResolver::class)->latitude($this->address);
    }

    public function addressLongitude(): ?string
    {
        return app(EventAddressResolver::class)->longitude($this->address);
    }

    public function getAddressLabelAttribute(): ?string
    {
        return $this->addressLabel();
    }

    public function getLocationLabelAttribute(): ?string
    {
        return $this->locationLabel();
    }

    /**
     * @return array<int, string>
     */
    public function getAddressLinesAttribute(): array
    {
        return $this->addressLines();
    }

    public function getAddressCountryAttribute(): ?string
    {
        return $this->addressCountry();
    }

    public function getAddressTimezoneAttribute(): ?string
    {
        return $this->addressTimezone();
    }

    public function getAddressLatitudeAttribute(): ?string
    {
        return $this->addressLatitude();
    }

    public function getAddressLongitudeAttribute(): ?string
    {
        return $this->addressLongitude();
    }

    public function isPaidRegistration(): bool
    {
        return $this->registration_mode === 'paid'
            || $this->registration_mode === 'hybrid'
            || $this->product_id !== null
            || $this->variant_id !== null;
    }

    public function duplicateStrategy(): string
    {
        return $this->duplicate_strategy ?? (string) config('events.defaults.occurrence_duplicate_strategy', 'per_occurrence');
    }

    public function isWaitlistEnabled(): bool
    {
        return $this->waitlist_enabled;
    }

    public function requiresApproval(): bool
    {
        return $this->approval_required;
    }

    public function displayTimezone(?Model $viewer = null): string
    {
        return app(EventDisplayTimezoneResolver::class)->resolve($this, $viewer);
    }

    public function startsAtForDisplay(?Model $viewer = null): Carbon
    {
        return Carbon::instance($this->starts_at)->setTimezone($this->displayTimezone($viewer));
    }

    public function endsAtForDisplay(?Model $viewer = null): ?Carbon
    {
        return $this->ends_at !== null
            ? Carbon::instance($this->ends_at)->setTimezone($this->displayTimezone($viewer))
            : null;
    }

    private function resolvedParticipationMode(): OccurrenceParticipationMode
    {
        return $this->participation_mode instanceof OccurrenceParticipationMode
            ? $this->participation_mode
            : OccurrenceParticipationMode::RegistrationRequired;
    }
}
