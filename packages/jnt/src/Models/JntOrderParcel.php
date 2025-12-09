<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $order_id
 * @property int $sequence
 * @property string $tracking_number
 * @property string|null $actual_weight
 * @property string|null $length
 * @property string|null $width
 * @property string|null $height
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read JntOrder $order
 */
final class JntOrderParcel extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'sequence',
        'tracking_number',
        'actual_weight',
        'length',
        'width',
        'height',
        'metadata',
    ];

    public function getTable(): string
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        return $tables['order_parcels'] ?? $prefix . 'order_parcels';
    }

    /**
     * Get the order that owns this parcel.
     *
     * @return BelongsTo<JntOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(JntOrder::class, 'order_id');
    }

    /**
     * Get the volume (length * width * height) in cubic centimeters.
     */
    public function getVolume(): ?float
    {
        if ($this->length === null || $this->width === null || $this->height === null) {
            return null;
        }

        return (float) $this->length * (float) $this->width * (float) $this->height;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'metadata' => 'array',
        ];
    }
}
