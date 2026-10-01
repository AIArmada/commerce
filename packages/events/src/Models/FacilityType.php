<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Addressing\Traits\HasAddresses;
use AIArmada\Events\Database\Factories\FacilityTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $category
 * @property string|null $description
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_active Set false to retire a type that event facilities still reference.
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, VenueFacility> $venueFacilities
 */
final class FacilityType extends Model
{
    use HasAddresses;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'code', 'name', 'category', 'description', 'icon',
        'sort_order', 'is_active',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.facility_types', 'facility_types');
    }

    protected static function booted(): void
    {
        static::deleting(function (FacilityType $type): void {
            $referenced = EventFacility::query()
                ->withoutOwnerScope()
                ->where('facility_type_id', $type->getKey())
                ->exists();

            if ($referenced) {
                throw new InvalidArgumentException(
                    "Facility type [{$type->getAttribute('code')}] is referenced by event facilities and cannot be deleted. Set is_active=false to retire it instead."
                );
            }

            VenueFacility::query()->where('facility_type_id', $type->getKey())->delete();
        });
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
     * Place-facility values using this catalog entry.
     *
     * Deleting a type first checks every EventFacility row cross-owner; when
     * any event facility references the type the delete is rejected and all
     * rows are preserved. Retire referenced types with is_active=false.
     *
     * @return HasMany<VenueFacility, $this>
     */
    public function venueFacilities(): HasMany
    {
        return $this->hasMany(VenueFacility::class, 'facility_type_id');
    }

    /**
     * @param  Builder<FacilityType>  $query
     * @return Builder<FacilityType>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<FacilityType>  $query
     * @return Builder<FacilityType>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    protected static function newFactory(): FacilityTypeFactory
    {
        return FacilityTypeFactory::new();
    }
}
