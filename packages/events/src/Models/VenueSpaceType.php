<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\VenueSpaceTypeFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property string|null $applies_to_venue_type
 * @property int $sort_order
 * @property bool $is_active
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, EventLocation> $eventLocations
 */
final class VenueSpaceType extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'code', 'name', 'description', 'category',
        'applies_to_venue_type', 'sort_order', 'is_active',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.venue_space_types', 'venue_space_types');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<EventLocation, $this>
     */
    public function eventLocations(): HasMany
    {
        return $this->hasMany(EventLocation::class);
    }

    protected static function newFactory(): VenueSpaceTypeFactory
    {
        return VenueSpaceTypeFactory::new();
    }
}
