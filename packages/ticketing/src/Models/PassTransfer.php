<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Models;

use AIArmada\Ticketing\Database\Factories\PassTransferFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $pass_id
 * @property string|null $from_holder_id
 * @property string|null $to_holder_id
 * @property string|null $reason
 * @property string|null $transferred_by_type
 * @property string|null $transferred_by_id
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pass $pass
 * @property-read PassHolder|null $fromHolder
 * @property-read PassHolder|null $toHolder
 */
class PassTransfer extends Model
{
    use HasFactory;
    use HasUuids;

    protected static function newFactory(): PassTransferFactory
    {
        return PassTransferFactory::new();
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'pass_id',
        'from_holder_id',
        'to_holder_id',
        'reason',
        'transferred_by_type',
        'transferred_by_id',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('ticketing.database.tables.pass_transfers', 'pass_transfers');
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Pass, $this>
     */
    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    /**
     * @return BelongsTo<PassHolder, $this>
     */
    public function fromHolder(): BelongsTo
    {
        return $this->belongsTo(PassHolder::class, 'from_holder_id');
    }

    /**
     * @return BelongsTo<PassHolder, $this>
     */
    public function toHolder(): BelongsTo
    {
        return $this->belongsTo(PassHolder::class, 'to_holder_id');
    }
}
