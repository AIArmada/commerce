<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Maps\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Seating\Database\Factories\SeatFactory;
use AIArmada\Seating\Enums\SeatStatus;
use AIArmada\Seating\Features\Reservations\Api\ReservationsApi;
use AIArmada\Seating\Platform\ModelResolver;
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
 * @property SeatStatus $status
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

    protected static function booted(): void
    {
        static::deleting(function (self $seat): void {
            app(ReservationsApi::class)->deleteForSeat(
                seatId: (string) $seat->getKey(),
                ownerType: $seat->owner_type,
                ownerId: $seat->owner_id,
            );
        });
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $attributes = [
        'status' => SeatStatus::Available,
    ];

    protected $fillable = [
        'seat_section_id',
        'row_label',
        'seat_label',
        'row_number',
        'column_number',
        'category',
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
            'status' => SeatStatus::class,
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

    /** @return HasMany<Model, $this> */
    public function holds(): HasMany
    {
        return $this->hasMany(ModelResolver::seatHoldClass(), 'seat_id');
    }

    /** @return HasMany<Model, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ModelResolver::seatAllocationClass(), 'seat_id');
    }

    /** @return HasOne<Model, $this> */
    public function activeAllocation(): HasOne
    {
        return $this->hasOne(ModelResolver::seatAllocationClass(), 'seat_id')
            ->where('status', 'active');
    }

    /** @param Builder<Seat> $query */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', SeatStatus::Available);
    }

    /** @param Builder<Seat> $query */
    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('status', SeatStatus::Blocked);
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
