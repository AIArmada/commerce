<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
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
    ];

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

        /** @var Builder<Occurrence> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'venue_id');
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

    /**
     * @return BelongsTo<Model, $this>
     */
    public function variant(): BelongsTo
    {
        /** @var class-string<Model> $variantModel */
        $variantModel = config('events.integrations.variant_model', Variant::class);

        return $this->belongsTo($variantModel, 'variant_id');
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
        if (! $this->status->acceptsRegistrations()) {
            return false;
        }

        $now = now();

        if ($this->registration_opens_at !== null && $this->registration_opens_at->isFuture()) {
            return false;
        }

        if ($this->registration_closes_at !== null && $this->registration_closes_at->lt($now)) {
            return false;
        }

        return true;
    }

    public function acceptsCheckIn(): bool
    {
        if (! $this->status->acceptsRegistrations()) {
            return false;
        }

        $now = now();

        if ($this->check_in_opens_at !== null && $this->check_in_opens_at->isFuture()) {
            return false;
        }

        if ($this->check_in_closes_at !== null && $this->check_in_closes_at->lt($now)) {
            return false;
        }

        return true;
    }
}
