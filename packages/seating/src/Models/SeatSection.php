<?php

declare(strict_types=1);

namespace AIArmada\Seating\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Seating\Database\Factories\SeatSectionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $seat_map_id
 * @property string $name
 * @property string $code
 * @property int $sort_order
 * @property int $capacity
 * @property string|null $color
 * @property array|null $metadata
 */
class SeatSection extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'seating.owner';

    protected static function newFactory(): SeatSectionFactory
    {
        return SeatSectionFactory::new();
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $attributes = [
        'sort_order' => 0,
    ];

    protected $fillable = [
        'seat_map_id',
        'name',
        'code',
        'sort_order',
        'capacity',
        'color',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('seating.database.tables.seat_sections', 'seat_sections');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'capacity' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<SeatMap, $this> */
    public function seatMap(): BelongsTo
    {
        return $this->belongsTo(SeatMap::class, 'seat_map_id');
    }

    /** @return HasMany<Seat, $this> */
    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class, 'seat_section_id');
    }
}
