<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle\Actions;

use AIArmada\Orders\Actions\Concerns\AssertsOrderOwnerBoundary;
use AIArmada\Orders\Contracts\PackageShipmentVerifier;
use AIArmada\Orders\Enums\OrderItemStatus;
use AIArmada\Orders\Events\OrderShipped;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Shipped;
use AIArmada\Orders\Support\PackageUnit;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Transition from Processing → Shipped.
 *
 * This transition is triggered when a shipment is created.
 * It records the shipping details and updates the order state.
 */
final class ShipmentCreated extends Transition
{
    use AssertsOrderOwnerBoundary;

    public function __construct(
        private Order $order,
        private string $carrier,
        private string $trackingNumber,
        private ?string $shipmentId = null,
        /** @var array<string, mixed> */
        private array $metadata = [],
    ) {}

    public function handle(): Order
    {
        $this->assertOwnerBoundaryForMutation($this->order, __METHOD__);

        return DB::transaction(function (): Order {
            $this->order->newQuery()->lockForUpdate()->findOrFail($this->order->id);
            $this->order->refresh();
            $this->assertOwnerBoundaryForMutation($this->order, __METHOD__);

            if ($this->order->items()->get()->contains(fn ($item): bool => PackageUnit::hasShippableComponents($item))) {
                if (! app()->bound(PackageShipmentVerifier::class)) {
                    throw new DomainException('Package fulfillment requires the shipping integration.');
                }

                if (! app(PackageShipmentVerifier::class)->allItemsShipped((string) $this->order->getKey(), $this->shipmentId)
                    || $this->order->isShipped()) {
                    return $this->order;
                }
            }

            return $this->markShipped();
        });
    }

    private function markShipped(): Order
    {
        $shippedAt = CarbonImmutable::now();

        $existingMetadata = $this->order->metadata ?? [];
        if (! is_array($existingMetadata)) {
            $existingMetadata = [];
        }

        $existingShipping = Arr::get($existingMetadata, 'shipping', []);
        if (! is_array($existingShipping)) {
            $existingShipping = [];
        }

        $existingShippingMetadata = Arr::get($existingShipping, 'metadata', []);
        if (! is_array($existingShippingMetadata)) {
            $existingShippingMetadata = [];
        }

        $shipping = array_merge($existingShipping, [
            'carrier' => $this->carrier,
            'tracking_number' => $this->trackingNumber,
            'shipment_id' => $this->shipmentId,
            'shipped_at' => $shippedAt->toIso8601String(),
        ]);

        if ($this->metadata !== []) {
            $shipping['metadata'] = array_merge($existingShippingMetadata, $this->metadata);
        }

        $existingMetadata['shipping'] = $shipping;
        $this->order->metadata = $existingMetadata;
        $this->order->shipped_at = $shippedAt;

        // Mark all active items as shipped
        $this->order->items()
            ->where('status', OrderItemStatus::Active)
            ->update([
                'status' => OrderItemStatus::Shipped,
                'shipped_at' => $shippedAt,
            ]);

        $this->order->status->transitionTo(Shipped::class);

        // Dispatch event
        event(new OrderShipped(
            $this->order,
            $this->carrier,
            $this->trackingNumber,
            $this->shipmentId
        ));

        return $this->order;
    }
}
