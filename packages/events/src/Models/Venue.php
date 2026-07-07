<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Contacting\Concerns\HasContactMethods;
use AIArmada\Contacting\Concerns\HasSocialProfiles;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Events\Database\Factories\VenueFactory;
use AIArmada\Events\Models\Concerns\Addressable;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $parent_venue_id
 * @property string $name
 * @property string $slug
 * @property string $venue_type
 * @property string|null $line1
 * @property string|null $line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postcode
 * @property string|null $country_code
 * @property string|null $country
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $google_place_id
 * @property string|null $google_maps_url
 * @property string|null $waze_url
 * @property string|null $map_url
 * @property string|null $directions
 * @property CarbonImmutable|null $geocoded_at
 * @property string|null $geocoding_source
 * @property string $status
 * @property string $visibility
 * @property-read string|null $phone
 * @property-read string|null $email
 * @property-read string|null $website_url
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Venue|null $parentVenue
 * @property-read Collection<int, Venue> $childVenues
 * @property-read Collection<int, VenueSpace> $spaces
 * @property-read Collection<int, VenueFacility> $facilities
 * @property-read Collection<int, EventLocation> $eventLocations
 */
class Venue extends Model implements HasMedia
{
    use Addressable;
    use HasContactMethods;
    use HasFactory;
    use HasSocialProfiles;
    use UsesEventUuid;
    use InteractsWithMedia;

    protected $fillable = [
        'parent_venue_id',
        'name', 'slug', 'venue_type',
        'line1', 'line2',
        'city', 'state', 'postcode', 'country_code', 'country',
        'latitude', 'longitude',
        'google_place_id', 'google_maps_url', 'waze_url', 'map_url',
        'directions',
        'geocoded_at', 'geocoding_source',
        'status', 'visibility',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.venues', 'venues');
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'geocoded_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->resolvePrimaryContactValue('phone');
    }

    public function getEmailAttribute(): ?string
    {
        return $this->resolvePrimaryContactValue('email');
    }

    public function getWebsiteUrlAttribute(): ?string
    {
        return $this->resolvePrimaryContactValue('website');
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function parentVenue(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_venue_id');
    }

    /**
     * @return HasMany<Venue, $this>
     */
    public function childVenues(): HasMany
    {
        return $this->hasMany(self::class, 'parent_venue_id');
    }

    /**
     * @return HasMany<VenueSpace, $this>
     */
    public function spaces(): HasMany
    {
        return $this->hasMany(VenueSpace::class);
    }

    /**
     * @return HasMany<VenueFacility, $this>
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(VenueFacility::class);
    }

    /**
     * @return MorphMany<EventLocation, $this>
     */
    public function eventLocations(): MorphMany
    {
        return $this->morphMany(EventLocation::class, 'locationable');
    }

    private function resolvePrimaryContactValue(string $type): ?string
    {
        $contactMethod = $this->primaryContactMethod($type);

        if (! $contactMethod instanceof ContactMethod) {
            return null;
        }

        $value = $contactMethod->normalized_value ?? $contactMethod->value;

        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }

    protected static function newFactory(): VenueFactory
    {
        return VenueFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
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
