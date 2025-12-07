<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shipment_id
 * @property string $format
 * @property string|null $size
 * @property string|null $url
 * @property string|null $content
 * @property \Illuminate\Support\Carbon $generated_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Shipment $shipment
 */
class ShipmentLabel extends Model
{
    protected $table = 'shipment_labels';

    protected $fillable = [
        'shipment_id',
        'format',
        'size',
        'url',
        'content',
        'generated_at',
    ];

    /**
     * @return BelongsTo<Shipment, ShipmentLabel>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function hasUrl(): bool
    {
        return $this->url !== null;
    }

    public function hasContent(): bool
    {
        return $this->content !== null;
    }

    public function getDecodedContent(): ?string
    {
        if ($this->content === null) {
            return null;
        }

        return base64_decode($this->content);
    }

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }
}
