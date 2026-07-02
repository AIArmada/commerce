<?php

declare(strict_types=1);

namespace AIArmada\Seating\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Seating\Database\Factories\SeatAllocationFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $seat_id
 * @property string|null $allocated_to_type
 * @property string|null $allocated_to_id
 * @property string|null $reference
 * @property CarbonImmutable|null $allocated_at
 * @property CarbonImmutable|null $released_at
 * @property CarbonImmutable|null $revoked_at
 * @property string $state
 * @property array|null $metadata
 */
class SeatAllocation extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'seating.owner';

    protected static function newFactory(): SeatAllocationFactory
    {
        return SeatAllocationFactory::new();
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $attributes = [
        'state' => 'active',
    ];

    protected $fillable = [
        'seat_id',
        'allocated_to_type',
        'allocated_to_id',
        'reference',
        'allocated_at',
        'released_at',
        'revoked_at',
        'state',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('seating.database.tables.seat_allocations', 'seat_allocations');
    }

    protected function casts(): array
    {
        return [
            'allocated_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function isActive(): bool
    {
        return $this->state === 'active';
    }

    /** @return BelongsTo<Seat, $this> */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class, 'seat_id');
    }

    public function allocatedTo(): MorphTo
    {
        return $this->morphTo('allocated_to', 'allocated_to_type', 'allocated_to_id');
    }

    public function release(): void
    {
        $this->update(['state' => 'released', 'released_at' => now()]);
    }

    public function revoke(): void
    {
        $this->update(['state' => 'revoked', 'revoked_at' => now()]);
    }
}
