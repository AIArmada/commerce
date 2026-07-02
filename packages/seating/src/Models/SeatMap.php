<?php

declare(strict_types=1);

namespace AIArmada\Seating\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Seating\Database\Factories\SeatMapFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string|null $seatable_type
 * @property string|null $seatable_id
 * @property string $name
 * @property string|null $slug
 * @property int $version
 * @property string $status
 * @property array|null $layout_metadata
 */
class SeatMap extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'seating.owner';

    protected static function newFactory(): SeatMapFactory
    {
        return SeatMapFactory::new();
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $attributes = [
        'version' => 1,
        'status' => 'active',
    ];

    protected $fillable = [
        'seatable_type',
        'seatable_id',
        'name',
        'slug',
        'version',
        'status',
        'layout_metadata',
    ];

    public function getTable(): string
    {
        return config('seating.database.tables.seat_maps', 'seat_maps');
    }

    protected function casts(): array
    {
        return [
            'layout_metadata' => 'array',
            'version' => 'integer',
        ];
    }

    public function seatable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<SeatSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(SeatSection::class, 'seat_map_id');
    }

    /** @param Builder<SeatMap> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** @param Builder<SeatMap> $query */
    public function scopeForHost(Builder $query, Model $host): Builder
    {
        return $query->where('seatable_type', $host->getMorphClass())
            ->where('seatable_id', $host->getKey());
    }
}
