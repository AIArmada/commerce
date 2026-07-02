<?php

declare(strict_types=1);

namespace AIArmada\Seating\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Seating\Database\Factories\SeatFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $seat_section_id
 * @property string $row_label
 * @property string $seat_label
 * @property int $row_number
 * @property int $column_number
 * @property string|null $category
 * @property int|null $price_modifier
 * @property string $status
 * @property array|null $metadata
 */
class Seat extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'seating.owner';

    protected static function newFactory(): SeatFactory
    {
        return SeatFactory::new();
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $attributes = [
        'status' => 'available',
    ];

    protected $fillable = [
        'seat_section_id',
        'row_label',
        'seat_label',
        'row_number',
        'column_number',
        'category',
        'price_modifier',
        'status',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('seating.database.tables.seats', 'seats');
    }

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'column_number' => 'integer',
            'price_modifier' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function label(): string
    {
        return $this->row_label . $this->seat_label;
    }

    /** @return BelongsTo<SeatSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(SeatSection::class, 'seat_section_id');
    }

    /** @return HasMany<SeatHold, $this> */
    public function holds(): HasMany
    {
        return $this->hasMany(SeatHold::class, 'seat_id');
    }

    /** @return HasMany<SeatAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(SeatAllocation::class, 'seat_id');
    }

    /** @return HasOne<SeatAllocation, $this> */
    public function activeAllocation(): HasOne
    {
        return $this->hasOne(SeatAllocation::class, 'seat_id')
            ->where('state', 'active');
    }

    /** @param Builder<Seat> $query */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    /** @param Builder<Seat> $query */
    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('status', 'blocked');
    }

    /** @param Builder<Seat> $query */
    public function scopeInCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /** @param Builder<Seat> $query */
    public function scopeInSection(Builder $query, SeatSection $section): Builder
    {
        return $query->where('seat_section_id', $section->id);
    }
}
