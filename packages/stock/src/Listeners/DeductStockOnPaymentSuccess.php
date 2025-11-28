<?php

declare(strict_types=1);

namespace AIArmada\Stock\Listeners;

use AIArmada\Stock\Contracts\StockableInterface;
use AIArmada\Stock\Services\StockReservationService;
use AIArmada\Stock\Traits\HasStock;
use Illuminate\Database\Eloquent\Model;

/**
 * Listener that deducts stock when a payment succeeds.
 *
 * This listener works with any payment event that has:
 * - A `billable` property (the user/customer)
 * - A `purchase` or `payload` property with cart/line item data
 *
 * It automatically handles:
 * - CashierChip PaymentSucceeded event
 * - Any custom payment success event with the same structure
 *
 * To queue this listener, extend it in your application and implement ShouldQueue.
 */
final class DeductStockOnPaymentSuccess
{
    public function __construct(
        private readonly StockReservationService $reservationService
    ) {}

    /**
     * Handle any payment success event.
     */
    public function handle(object $event): void
    {
        // Try to get cart ID from event
        $cartId = $this->extractCartId($event);
        $orderId = $this->extractOrderId($event);

        if ($cartId !== null) {
            // Commit all reservations for this cart
            $this->reservationService->commitReservations($cartId, $orderId);

            return;
        }

        // Fallback: Try to deduct stock from line items in the event
        $lineItems = $this->extractLineItems($event);

        foreach ($lineItems as $item) {
            $stockable = $item['stockable'] ?? null;
            $quantity = $item['quantity'] ?? 1;

            if ($stockable instanceof Model && $this->hasStockTrait($stockable)) {
                $this->reservationService->deductStock(
                    $stockable,
                    (int) $quantity,
                    'sale',
                    $orderId
                );
            }
        }
    }

    /**
     * Extract cart ID from event.
     */
    private function extractCartId(object $event): ?string
    {
        // Check common property names
        foreach (['cart_id', 'cartId', 'cart'] as $prop) {
            if (property_exists($event, $prop)) {
                $value = $event->{$prop};

                if (is_string($value) && $value !== '') {
                    return $value;
                }

                if (is_object($value) && method_exists($value, 'getId')) {
                    $id = $value->getId();

                    return $id !== null ? (string) $id : null;
                }
            }
        }

        // Check in purchase/payload array
        $data = $this->extractPayloadData($event);

        return $data['cart_id'] ?? $data['metadata']['cart_id'] ?? null;
    }

    /**
     * Extract order ID from event.
     */
    private function extractOrderId(object $event): ?string
    {
        foreach (['order_id', 'orderId', 'reference', 'purchase_id'] as $prop) {
            if (property_exists($event, $prop)) {
                $value = $event->{$prop};

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        $data = $this->extractPayloadData($event);

        return $data['order_id'] ?? $data['reference'] ?? $data['id'] ?? null;
    }

    /**
     * Extract payload data from event.
     *
     * @return array<string, mixed>
     */
    private function extractPayloadData(object $event): array
    {
        foreach (['purchase', 'payload', 'data', 'payment'] as $prop) {
            if (property_exists($event, $prop) && is_array($event->{$prop})) {
                return $event->{$prop};
            }
        }

        return [];
    }

    /**
     * Extract line items from event.
     *
     * @return array<array{stockable?: Model, quantity?: int|string}>
     */
    private function extractLineItems(object $event): array
    {
        $data = $this->extractPayloadData($event);

        // Check for line_items in payload
        if (isset($data['line_items']) && is_array($data['line_items'])) {
            return $data['line_items'];
        }

        // Check for items property
        if (property_exists($event, 'items') && is_iterable($event->items)) {
            return iterator_to_array($event->items);
        }

        return [];
    }

    /**
     * Check if model uses HasStock trait.
     */
    private function hasStockTrait(Model $model): bool
    {
        return in_array(HasStock::class, class_uses_recursive($model), true)
            || $model instanceof StockableInterface;
    }
}
