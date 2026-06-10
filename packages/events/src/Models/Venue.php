<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Contracts\EventAddressable;
use AIArmada\Events\Data\EventAddressData;
use AIArmada\Events\Enums\VenueStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $slug
 * @property string $location_type
 * @property string|null $contact_name
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $line1
 * @property string|null $line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postcode
 * @property string $country
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $map_url
 * @property string|null $external_id
 * @property string|null $timezone
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 */
class Venue extends Model implements Auditable, EventAddressable
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
        'status',
        'name',
        'slug',
        'location_type',
        'contact_name',
        'contact_email',
        'contact_phone',
        'line1',
        'line2',
        'city',
        'state',
        'postcode',
        'country',
        'latitude',
        'longitude',
        'map_url',
        'external_id',
        'timezone',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => VenueStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'metadata' => 'array',
        ];
    }

    protected $attributes = [
        'status' => 'active',
        'country' => 'MY',
        'location_type' => 'physical',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.venues', 'event_venues');
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

        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    /**
     * @return MorphMany<Occurrence, $this>
     */
    public function occurrences(): MorphMany
    {
        return $this->morphMany(Occurrence::class, 'address');
    }

    public function eventAddressData(): EventAddressData
    {
        $locationParts = collect([$this->city, $this->state, $this->postcode])
            ->filter(static fn (mixed $value): bool => is_string($value) && mb_trim($value) !== '')
            ->values()
            ->all();

        $lines = array_values(array_filter([
            $this->line1,
            $this->line2,
            $locationParts !== [] ? implode(', ', $locationParts) : null,
            $this->country,
        ], static fn (mixed $value): bool => is_string($value) && mb_trim($value) !== ''));

        return new EventAddressData(
            label: $this->name,
            lines: $lines,
            latitude: $this->latitude,
            longitude: $this->longitude,
            country: $this->country,
            timezone: $this->timezone,
        );
    }
}
