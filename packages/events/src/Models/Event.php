<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Contracts\EventDisplayTimezoneResolver;
use AIArmada\Events\Contracts\EventSearchPayloadResolver;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Support\CommerceIntegration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $organizer_type
 * @property string|null $organizer_id
 * @property string|null $event_series_id
 * @property string|null $product_id
 * @property string $name
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property EventStatus $status
 * @property EventModerationStatus $moderation_status
 * @property EventVisibility $visibility
 * @property int|null $default_duration_minutes
 * @property string|null $default_timezone
 * @property Carbon|null $published_at
 * @property Carbon|null $public_starts_at
 * @property Carbon|null $public_ends_at
 * @property array<string, mixed>|null $media_references
 * @property array<string, mixed>|null $taxonomy
 * @property string|null $search_keywords
 * @property array<string, mixed>|null $metadata
 */
class Event extends Model implements Auditable
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
        'media_references',
        'taxonomy',
        'search_keywords',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'moderation_status' => EventModerationStatus::class,
            'visibility' => EventVisibility::class,
            'default_duration_minutes' => 'integer',
            'published_at' => 'datetime',
            'public_starts_at' => 'datetime',
            'public_ends_at' => 'datetime',
            'media_references' => 'array',
            'taxonomy' => 'array',
            'metadata' => 'array',
        ];
    }

    protected $attributes = [
        'status' => 'draft',
        'moderation_status' => 'approved',
        'visibility' => 'public',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.events', 'commerce_events');
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

        /** @var Builder<Event> $scoped */
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
    public function scopePubliclyAccessible(Builder $query, ?Carbon $now = null): Builder
    {
        $now ??= now('UTC');

        return $this->constrainPublicWindow(
            $query
                ->where($this->qualifyColumn('status'), EventStatus::Active->value)
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
     * @return BelongsTo<EventSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(EventSeries::class, 'event_series_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function organizer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<EventSpeaker, $this>
     */
    public function speakers(): HasMany
    {
        return $this->hasMany(EventSpeaker::class, 'event_id')
            ->orderBy((new EventSpeaker)->qualifyColumn('order_column'))
            ->orderBy((new EventSpeaker)->qualifyColumn('display_name'));
    }

    /**
     * @return HasMany<Occurrence, $this>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class, 'event_id');
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

    public function isPubliclyAccessible(?Carbon $now = null): bool
    {
        $now ??= now('UTC');

        if ($this->status !== EventStatus::Active) {
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
        if (! $this->isPubliclyAccessible($now)) {
            return false;
        }

        return $this->visibility->isDiscoverable();
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
        $taxonomy = $this->taxonomy ?? [];

        if ($group === null) {
            return $taxonomy;
        }

        $terms = $taxonomy[$group] ?? [];

        return is_array($terms) ? $terms : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
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
