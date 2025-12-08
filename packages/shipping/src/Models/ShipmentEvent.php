<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Models;

use AIArmada\Shipping\Enums\TrackingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $shipment_id
 * @property string|null $carrier_event_code
 * @property TrackingStatus $normalized_status
 * @property string|null $description
 * @property string|null $location
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property string|null $postal_code
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property array|null $raw_data
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Shipment $shipment
 */
class ShipmentEvent extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'shipment_id',
        'carrier_event_code',
        'normalized_status',
        'description',
        'location',
        'city',
        'state',
        'country',
        'postal_code',
        'occurred_at',
        'raw_data',
    ];

    public function getTable(): string
    {
        return config('shipping.database.tables.shipment_events', 'shipment_events');
    }

    /**
     * @return BelongsTo<Shipment, ShipmentEvent>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function getFormattedLocation(): string
    {
        return collect([
            $this->city,
            $this->state,
            $this->country,
        ])->filter()->implode(', ');
    }

    public function isException(): bool
    {
        return $this->normalized_status->isException();
    }

    public function isTerminal(): bool
    {
        return $this->normalized_status->isTerminal();
    }

    protected function casts(): array
    {
        return [
            'normalized_status' => TrackingStatus::class,
            'occurred_at' => 'datetime',
            'raw_data' => 'array',
        ];
    }
}
