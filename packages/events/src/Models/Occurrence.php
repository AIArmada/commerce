<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Contracts\EventDisplayTimezoneResolver;
use AIArmada\Events\Enums\OccurrenceParticipationMode;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Support\CommerceIntegration;
use AIArmada\Events\Support\ConfiguredEventModel;
use AIArmada\Events\Support\LifecyclePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $event_id
 * @property string|null $venue_id
 * @property string|null $product_id
 * @property string|null $variant_id
 * @property string|null $name
 * @property OccurrenceStatus $status
 * @property OccurrenceParticipationMode $participation_mode
 * @property int|null $capacity
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property string $timezone
 * @property Carbon|null $registration_opens_at
 * @property Carbon|null $registration_closes_at
 * @property Carbon|null $check_in_opens_at
 * @property Carbon|null $check_in_closes_at
 * @property array<string, mixed>|null $metadata
 */
class Occurrence extends Model implements Auditable
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
        'venue_id',
        'product_id',
        'variant_id',
        'name',
        'status',
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
    ];

    protected function casts(): array
    {
        return [
            'status' => OccurrenceStatus::class,
            'participation_mode' => OccurrenceParticipationMode::class,
            'capacity' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'check_in_opens_at' => 'datetime',
            'check_in_closes_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected $attributes = [
        'status' => 'draft',
        'participation_mode' => 'registration_required',
    ];

    protected static function booted(): void
    {
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
        return config('events.database.tables.occurrences', 'commerce_event_occurrences');
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

        /** @var Builder<Occurrence> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(
            ConfiguredEventModel::classFor('events.models.event', Event::class),
            'event_id',
        );
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(
            ConfiguredEventModel::classFor('events.models.venue', Venue::class),
            'venue_id',
        );
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(
            CommerceIntegration::requireModelClass('product_model', 'products'),
            'product_id',
        );
    }

    /**
     * @return BelongsTo<Model, $this>
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

    public function displayTimezone(?Model $viewer = null): string
    {
        return app(EventDisplayTimezoneResolver::class)->resolve($this, $viewer);
    }

    public function startsAtForDisplay(?Model $viewer = null): Carbon
    {
        return $this->starts_at->copy()->setTimezone($this->displayTimezone($viewer));
    }

    public function endsAtForDisplay(?Model $viewer = null): ?Carbon
    {
        return $this->ends_at?->copy()->setTimezone($this->displayTimezone($viewer));
    }

    private function resolvedParticipationMode(): OccurrenceParticipationMode
    {
        return $this->participation_mode instanceof OccurrenceParticipationMode
            ? $this->participation_mode
            : OccurrenceParticipationMode::RegistrationRequired;
    }
}
