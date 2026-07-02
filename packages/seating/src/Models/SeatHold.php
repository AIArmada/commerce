<?php

declare(strict_types=1);

namespace AIArmada\Seating\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Seating\Database\Factories\SeatHoldFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string|null $held_by_type
 * @property string|null $held_by_id
 * @property string $seat_id
 * @property string|null $reference
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $converted_at
 * @property array|null $metadata
 */
class SeatHold extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'seating.owner';

    protected static function newFactory(): SeatHoldFactory
    {
        return SeatHoldFactory::new();
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'held_by_type',
        'held_by_id',
        'seat_id',
        'reference',
        'expires_at',
        'converted_at',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('seating.database.tables.seat_holds', 'seat_holds');
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'converted_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }

    /** @return BelongsTo<Seat, $this> */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class, 'seat_id');
    }

    public function isConverted(): bool
    {
        return $this->converted_at !== null;
    }

    public function markConverted(): void
    {
        $this->update(['converted_at' => now()]);
    }

    public function heldBy(): MorphTo
    {
        return $this->morphTo('held_by', 'held_by_type', 'held_by_id');
    }
}
