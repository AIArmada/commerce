<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Products\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $event_series_id
 * @property string|null $product_id
 * @property string $name
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property EventStatus $status
 * @property int|null $default_duration_minutes
 * @property string|null $default_timezone
 * @property array<string, mixed>|null $metadata
 */
class Event extends Model
{
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'event_series_id',
        'product_id',
        'name',
        'slug',
        'summary',
        'description',
        'status',
        'default_duration_minutes',
        'default_timezone',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'default_duration_minutes' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected $attributes = [
        'status' => 'draft',
    ];

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

        /** @var Builder<Event> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    /**
     * @return BelongsTo<EventSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(EventSeries::class, 'event_series_id');
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
        /** @var class-string<Model> $productModel */
        $productModel = config('events.integrations.product_model', Product::class);

        return $this->belongsTo($productModel, 'product_id');
    }
}
