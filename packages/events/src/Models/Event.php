<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Actions\SynchronizeEventContent;
use AIArmada\Events\Contracts\EventDisplayTimezoneResolver;
use AIArmada\Events\Contracts\EventRelationalContentSubject;
use AIArmada\Events\Contracts\EventSearchPayloadResolver;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\EventStructure;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Exceptions\InvalidEventStatusTransition;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\Events\Support\Integration\ConfiguredEventModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $organizer_type
 * @property string|null $organizer_id
 * @property string|null $event_series_id
 * @property string|null $parent_event_id
 * @property string|null $product_id
 * @property string $name
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property EventStatus $status
 * @property EventModerationStatus $moderation_status
 * @property EventVisibility $visibility
 * @property EventStructure $structure
 * @property int|null $default_duration_minutes
 * @property string|null $default_timezone
 * @property Carbon|null $published_at
 * @property Carbon|null $public_starts_at
 * @property Carbon|null $public_ends_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $postponed_at
 * @property Carbon|null $delayed_at
 * @property string|null $last_state_change_actor_type
 * @property string|null $last_state_change_actor_id
 * @property string|null $last_state_change_note
 * @property Carbon|null $activated_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $last_state_change_at
 * @property array<string, mixed>|null $media_references
 * @property array<string, mixed>|null $taxonomy
 * @property string|null $search_keywords
 * @property array<string, mixed>|null $metadata
 * @property bool $registration_required
 */
class Event extends Model implements Auditable, EventRelationalContentSubject
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
        'event_series_id',
        'parent_event_id',
        'organizer_type',
        'organizer_id',
        'product_id',
        'name',
        'slug',
        'summary',
        'description',
        'status',
        'moderation_status',
        'visibility',
        'default_duration_minutes',
        'default_timezone',
        'published_at',
        'public_starts_at',
        'public_ends_at',
        'cancelled_at',
        'postponed_at',
        'delayed_at',
        'last_state_change_actor_type',
        'last_state_change_actor_id',
        'last_state_change_note',
        'activated_at',
        'archived_at',
        'last_state_change_at',
        'media_references',
        'taxonomy',
        'search_keywords',
        'metadata',
        'structure',
        'registration_required',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'moderation_status' => EventModerationStatus::class,
            'visibility' => EventVisibility::class,
            'structure' => EventStructure::class,
            'default_duration_minutes' => 'integer',
            'published_at' => 'immutable_datetime',
            'public_starts_at' => 'immutable_datetime',
            'public_ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'postponed_at' => 'immutable_datetime',
            'delayed_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'last_state_change_at' => 'immutable_datetime',
            'media_references' => 'array',
            'taxonomy' => 'array',
            'metadata' => 'array',
            'registration_required' => 'boolean',
        ];
    }

    protected $attributes = [
        'status' => 'draft',
        'moderation_status' => 'approved',
        'visibility' => 'public',
        'structure' => 'standalone',
    ];

    public function getTitleAttribute(): string
    {
        return $this->name;
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if ($event->getAttribute('structure') instanceof EventStructure) {
                return;
            }

            $configuredStructure = config('events.defaults.event_structure', EventStructure::Standalone->value);

            if (is_string($configuredStructure) && EventStructure::tryFrom($configuredStructure) instanceof EventStructure) {
                $event->setAttribute('structure', $configuredStructure);

                return;
            }

            $event->setAttribute('structure', EventStructure::Standalone->value);
        });

        static::updating(function (self $event): void {
            if (! $event->isDirty('status')) {
                return;
            }

            $current = $event->getOriginal('status');
            $next = $event->status;

            if (! $next instanceof EventStatus) {
                return;
            }

            $currentStatus = $current instanceof EventStatus
                ? $current
                : (is_string($current) ? EventStatus::tryFrom($current) : null);

            if ($currentStatus !== null && $currentStatus === $next) {
                return;
            }

            if ($currentStatus !== null && ! $currentStatus->canTransitionTo($next)) {
                throw InvalidEventStatusTransition::from($currentStatus, $next);
            }
        });

        static::saved(function (self $event): void {
            if (! $event->wasRecentlyCreated && ! $event->wasChanged(['taxonomy', 'media_references', 'metadata'])) {
                return;
            }

            app(SynchronizeEventContent::class)->handle($event, 'model_saved');
        });
    }

    public function getTable(): string
    {
        return config('events.database.tables.events', 'events');
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
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('moderation_status'), EventModerationStatus::Approved->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRootEvents(Builder $query): Builder
    {
        return $query->whereNull($this->qualifyColumn('parent_event_id'));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeChildEvents(Builder $query): Builder
    {
        return $query->whereNotNull($this->qualifyColumn('parent_event_id'));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithStructure(Builder $query, EventStructure | string $structure): Builder
    {
        $structureValue = $structure instanceof EventStructure ? $structure->value : $structure;

        return $query->where($this->qualifyColumn('structure'), $structureValue);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeStandalone(Builder $query): Builder
    {
        return $this->scopeRootEvents($this->scopeWithStructure($query, EventStructure::Standalone));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePrograms(Builder $query): Builder
    {
        return $this->scopeRootEvents($this->scopeWithStructure($query, EventStructure::Program));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSessions(Builder $query): Builder
    {
        return $this->scopeChildEvents($this->scopeWithStructure($query, EventStructure::Session));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePubliclyAccessible(Builder $query, ?Carbon $now = null): Builder
    {
        $now ??= now('UTC');

        return $this->constrainPublicWindow(
            $query
                ->whereIn($this->qualifyColumn('status'), [
                    EventStatus::Active->value,
                    EventStatus::Postponed->value,
                    EventStatus::Delayed->value,
                    EventStatus::Cancelled->value,
                    EventStatus::Archived->value,
                ])
                ->where($this->qualifyColumn('moderation_status'), EventModerationStatus::Approved->value)
                ->whereIn($this->qualifyColumn('visibility'), [
                    EventVisibility::Public->value,
                    EventVisibility::Unlisted->value,
                ]),
            $now,
        );
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePubliclyDiscoverable(Builder $query, ?Carbon $now = null): Builder
    {
        $now ??= now('UTC');

        return $this->constrainPublicWindow(
            $query
                ->where($this->qualifyColumn('status'), EventStatus::Active->value)
                ->where($this->qualifyColumn('moderation_status'), EventModerationStatus::Approved->value)
                ->where($this->qualifyColumn('visibility'), EventVisibility::Public->value),
            $now,
        );
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearchable(Builder $query, ?Carbon $now = null): Builder
    {
        return $this->scopePubliclyDiscoverable($query, $now);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUpcoming(Builder $query, ?Carbon $now = null): Builder
    {
        $now ??= now('UTC');

        return $query
            ->where($this->qualifyColumn('status'), EventStatus::Active->value)
            ->where(function (Builder $query) use ($now): void {
                $query
                    ->whereNull($this->qualifyColumn('public_starts_at'))
                    ->orWhere($this->qualifyColumn('public_starts_at'), '>=', $now);
            });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLive(Builder $query, ?Carbon $now = null): Builder
    {
        $now ??= now('UTC');

        return $query
            ->where($this->qualifyColumn('status'), EventStatus::Active->value)
            ->where(function (Builder $query) use ($now): void {
                $query
                    ->where(function (Builder $query) use ($now): void {
                        $query
                            ->whereNull($this->qualifyColumn('public_starts_at'))
                            ->orWhere($this->qualifyColumn('public_starts_at'), '<=', $now);
                    })
                    ->where(function (Builder $query) use ($now): void {
                        $query
                            ->whereNull($this->qualifyColumn('public_ends_at'))
                            ->orWhere($this->qualifyColumn('public_ends_at'), '>=', $now);
                    });
            });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDelayed(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), EventStatus::Delayed->value);
    }

    /**
     * @return BelongsTo<EventSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(EventSeries::class, 'event_series_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function parentEvent(): BelongsTo
    {
        return $this->belongsTo(ConfiguredEventModel::classFor('events.models.event', self::class), 'parent_event_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function organizer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<EventPerson, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(EventPerson::class, 'event_id')
            ->orderBy((new EventPerson)->qualifyColumn('order_column'))
            ->orderBy((new EventPerson)->qualifyColumn('display_name'));
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function childEvents(): HasMany
    {
        return $this->hasMany(ConfiguredEventModel::classFor('events.models.event', self::class), 'parent_event_id')
            ->orderBy($this->qualifyColumn('structure'))
            ->orderBy($this->qualifyColumn('name'));
    }

    /**
     * @return HasMany<Occurrence, $this>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class, 'event_id');
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
     * @return MorphMany<EventReferenceAssignment, $this>
     */
    public function references(): MorphMany
    {
        return $this->morphMany(EventReferenceAssignment::class, 'assignable')
            ->orderBy((new EventReferenceAssignment)->qualifyColumn('reference_kind'))
            ->orderBy((new EventReferenceAssignment)->qualifyColumn('order_column'));
    }

    /**
     * @return HasMany<EventSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(EventSubmission::class, 'event_id');
    }

    /**
     * @return HasMany<EventReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(EventReview::class, 'event_id');
    }

    /**
     * @return HasMany<EventChangeNotice, $this>
     */
    public function changeNotices(): HasMany
    {
        return $this->hasMany(EventChangeNotice::class, 'event_id');
    }

    /**
     * @return HasMany<EventAttendance, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(EventAttendance::class, 'event_id');
    }

    /**
     * @return HasMany<EventEngagement, $this>
     */
    public function engagements(): HasMany
    {
        return $this->hasMany(EventEngagement::class, 'event_id');
    }

    /**
     * @return HasManyThrough<EventAgendaItem, Occurrence, $this>
     */
    public function agendaItems(): HasManyThrough
    {
        return $this->hasManyThrough(EventAgendaItem::class, Occurrence::class, 'event_id', 'occurrence_id')
            ->orderBy((new EventAgendaItem)->qualifyColumn('order_column'))
            ->orderBy((new EventAgendaItem)->qualifyColumn('starts_at'))
            ->orderBy((new EventAgendaItem)->qualifyColumn('segment_key'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            CommerceIntegration::requireModelClass('product_model', 'products'),
            'product_id',
        );
    }

    public function isPubliclyAccessible(?Carbon $now = null): bool
    {
        $now ??= now('UTC');

        if (! $this->status->isPubliclyVisible()) {
            return false;
        }

        if (! $this->moderation_status->isPubliclyVisible()) {
            return false;
        }

        if (! $this->visibility->isPubliclyAccessible()) {
            return false;
        }

        return $this->isInsidePublicWindow($now);
    }

    public function isPubliclyDiscoverable(?Carbon $now = null): bool
    {
        if ($this->status !== EventStatus::Active) {
            return false;
        }

        if (! $this->moderation_status->isPubliclyVisible()) {
            return false;
        }

        if (! $this->visibility->isDiscoverable()) {
            return false;
        }

        return $this->isInsidePublicWindow($now ?? now('UTC'));
    }

    public function isEngageable(): bool
    {
        return $this->status->isEngageable();
    }

    public function isRoot(): bool
    {
        return $this->parent_event_id === null;
    }

    public function isChild(): bool
    {
        return $this->parent_event_id !== null;
    }

    public function isStandalone(): bool
    {
        return $this->structure->isStandalone();
    }

    public function isProgram(): bool
    {
        return $this->structure->isProgram();
    }

    public function isSession(): bool
    {
        return $this->structure->isSession();
    }

    public function displayTimezone(?Model $viewer = null): string
    {
        return app(EventDisplayTimezoneResolver::class)->resolve($this, $viewer);
    }

    /**
     * @return array<string, string>
     */
    public function mediaCollections(): array
    {
        $collections = config('events.media.collections', []);

        return is_array($collections)
            ? array_filter($collections, static fn (mixed $value): bool => is_string($value) && mb_trim($value) !== '')
            : [];
    }

    /**
     * @return array<int, mixed>|array<string, mixed>
     */
    public function taxonomyTerms(?string $group = null): array
    {
        $classifications = $this->relationLoaded('classifications')
            ? $this->classifications
            : $this->classifications()->orderBy('group_key')->orderBy('order_column')->get();

        if ($classifications->isNotEmpty()) {
            $grouped = $classifications
                ->sortBy('order_column')
                ->groupBy('group_key')
                ->map(static fn (Collection $items): array => $items->pluck('term_key')->values()->all())
                ->all();

            if ($group === null) {
                return $grouped;
            }

            return $grouped[$group] ?? [];
        }

        $taxonomy = $this->taxonomy ?? [];

        if ($group === null) {
            return $taxonomy;
        }

        $terms = $taxonomy[$group] ?? [];

        return is_array($terms) ? $terms : [];
    }

    /**
     * @return array<int, mixed>|array<string, mixed>
     */
    public function assetReferences(?string $role = null): array
    {
        $assets = $this->relationLoaded('assets')
            ? $this->assets
            : $this->assets()->orderBy('role_key')->orderBy('order_column')->get();

        if ($assets->isNotEmpty()) {
            $grouped = $assets
                ->sortBy('order_column')
                ->groupBy('role_key')
                ->map(static function (Collection $items): array | string {
                    $payload = $items
                        ->map(static fn (EventAsset $asset): string => $asset->url ?? $asset->provider_reference ?? $asset->title ?? '')
                        ->filter(static fn (string $value): bool => $value !== '')
                        ->values()
                        ->all();

                    if (count($payload) === 1) {
                        return $payload[0];
                    }

                    return $payload;
                })
                ->all();

            if ($role === null) {
                return $grouped;
            }

            $rolePayload = $grouped[$role] ?? [];

            return is_array($rolePayload) ? $rolePayload : [$rolePayload];
        }

        $mediaReferences = $this->media_references ?? [];

        if ($role === null) {
            return $mediaReferences;
        }

        $reference = $mediaReferences[$role] ?? [];

        return is_array($reference) ? $reference : [$reference];
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
                    ->map(static fn (EventReferenceAssignment $reference): array => [
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

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['classifications', 'assets', 'references', 'people']);

        return app(EventSearchPayloadResolver::class)->toSearchableArray($this);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    private function constrainPublicWindow(Builder $query, Carbon $now): Builder
    {
        return $query
            ->where(function (Builder $query) use ($now): void {
                $query
                    ->whereNull($this->qualifyColumn('published_at'))
                    ->orWhere($this->qualifyColumn('published_at'), '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query
                    ->whereNull($this->qualifyColumn('public_starts_at'))
                    ->orWhere($this->qualifyColumn('public_starts_at'), '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query
                    ->whereNull($this->qualifyColumn('public_ends_at'))
                    ->orWhere($this->qualifyColumn('public_ends_at'), '>=', $now);
            });
    }

    private function isInsidePublicWindow(Carbon $now): bool
    {
        if ($this->published_at !== null && $this->published_at->gt($now)) {
            return false;
        }

        if ($this->public_starts_at !== null && $this->public_starts_at->gt($now)) {
            return false;
        }

        if ($this->public_ends_at !== null && $this->public_ends_at->lt($now)) {
            return false;
        }

        return true;
    }
}
