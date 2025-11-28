<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $order_id
 * @property string|null $tracking_number
 * @property string $customer_code
 * @property string $action_type
 * @property string|null $service_type
 * @property string|null $payment_type
 * @property string|null $express_type
 * @property string|null $status
 * @property string|null $sorting_code
 * @property string|null $third_sorting_code
 * @property string|null $chargeable_weight
 * @property int $package_quantity
 * @property string|null $package_weight
 * @property string|null $package_length
 * @property string|null $package_width
 * @property string|null $package_height
 * @property string|null $package_value
 * @property string|null $goods_type
 * @property string|null $offer_value
 * @property string|null $cod_value
 * @property string|null $insurance_value
 * @property \Illuminate\Support\Carbon|null $pickup_start_at
 * @property \Illuminate\Support\Carbon|null $pickup_end_at
 * @property \Illuminate\Support\Carbon|null $ordered_at
 * @property \Illuminate\Support\Carbon|null $last_synced_at
 * @property \Illuminate\Support\Carbon|null $last_tracked_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property string|null $last_status_code
 * @property string|null $last_status
 * @property bool $has_problem
 * @property string|null $remark
 * @property array<string, mixed>|null $sender
 * @property array<string, mixed>|null $receiver
 * @property array<string, mixed>|null $return_info
 * @property array<string, mixed>|null $offer_fee_info
 * @property array<string, mixed>|null $customs_info
 * @property array<string, mixed>|null $request_payload
 * @property array<string, mixed>|null $response_payload
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, JntOrderItem> $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int, JntOrderParcel> $parcels
 * @property-read \Illuminate\Database\Eloquent\Collection<int, JntTrackingEvent> $trackingEvents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, JntWebhookLog> $webhookLogs
 */
class JntOrder extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'tracking_number',
        'customer_code',
        'action_type',
        'service_type',
        'payment_type',
        'express_type',
        'status',
        'sorting_code',
        'third_sorting_code',
        'chargeable_weight',
        'package_quantity',
        'package_weight',
        'package_length',
        'package_width',
        'package_height',
        'package_value',
        'goods_type',
        'offer_value',
        'cod_value',
        'insurance_value',
        'pickup_start_at',
        'pickup_end_at',
        'ordered_at',
        'last_synced_at',
        'last_tracked_at',
        'delivered_at',
        'last_status_code',
        'last_status',
        'has_problem',
        'remark',
        'sender',
        'receiver',
        'return_info',
        'offer_fee_info',
        'customs_info',
        'request_payload',
        'response_payload',
        'metadata',
    ];

    public function getTable(): string
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        return $tables['orders'] ?? $prefix.'orders';
    }

    /**
     * Get the items for this order.
     *
     * @return HasMany<JntOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(JntOrderItem::class, 'order_id');
    }

    /**
     * Get the parcels for this order.
     *
     * @return HasMany<JntOrderParcel, $this>
     */
    public function parcels(): HasMany
    {
        return $this->hasMany(JntOrderParcel::class, 'order_id');
    }

    /**
     * Get the tracking events for this order.
     *
     * @return HasMany<JntTrackingEvent, $this>
     */
    public function trackingEvents(): HasMany
    {
        return $this->hasMany(JntTrackingEvent::class, 'order_id');
    }

    /**
     * Get the webhook logs for this order.
     *
     * @return HasMany<JntWebhookLog, $this>
     */
    public function webhookLogs(): HasMany
    {
        return $this->hasMany(JntWebhookLog::class, 'order_id');
    }

    /**
     * Check if the order has been delivered.
     */
    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    /**
     * Check if the order has any problems.
     */
    public function hasProblem(): bool
    {
        return $this->has_problem;
    }

    /**
     * Get the latest tracking event.
     */
    public function latestTrackingEvent(): ?JntTrackingEvent
    {
        return $this->trackingEvents()->latest('scan_time')->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'package_quantity' => 'integer',
            'has_problem' => 'boolean',
            'pickup_start_at' => 'datetime',
            'pickup_end_at' => 'datetime',
            'ordered_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_tracked_at' => 'datetime',
            'delivered_at' => 'datetime',
            'sender' => 'array',
            'receiver' => 'array',
            'return_info' => 'array',
            'offer_fee_info' => 'array',
            'customs_info' => 'array',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Boot the model and register cascade delete handlers.
     */
    protected static function booted(): void
    {
        static::deleting(function (JntOrder $order): void {
            // Application-level cascade delete
            $order->items()->delete();
            $order->parcels()->delete();
            $order->trackingEvents()->delete();
            $order->webhookLogs()->update(['order_id' => null]);
        });
    }
}
