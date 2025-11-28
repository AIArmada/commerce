<?php

declare(strict_types=1);

namespace AIArmada\Stock\Services;

use AIArmada\Stock\Contracts\StockableInterface;
use AIArmada\Stock\Events\LowStockDetected;
use AIArmada\Stock\Events\OutOfStock;
use AIArmada\Stock\Events\StockDeducted;
use AIArmada\Stock\Events\StockReleased;
use AIArmada\Stock\Events\StockReserved;
use AIArmada\Stock\Models\StockReservation;
use AIArmada\Stock\Models\StockTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing stock reservations during checkout.
 *
 * Stock reservations prevent overselling by temporarily holding stock
 * while customers complete their checkout process.
 */
final class StockReservationService
{
    public function __construct(
        private readonly StockService $stockService
    ) {}

    /**
     * Check if an event type is enabled in config.
     */
    private function isEventEnabled(string $type): bool
    {
        return (bool) config("stock.events.{$type}", true);
    }

    /**
     * Reserve stock for a cart.
     *
     * @param  Model  $stockable  The product/variant being reserved
     * @param  int  $quantity  Quantity to reserve
     * @param  string  $cartId  Cart identifier
     * @param  int  $ttlMinutes  Reservation expiry time in minutes
     */
    public function reserve(
        Model $stockable,
        int $quantity,
        string $cartId,
        ?int $ttlMinutes = null
    ): ?StockReservation {
        $ttlMinutes ??= config('stock.cart.reservation_ttl', 30);

        return DB::transaction(function () use ($stockable, $quantity, $cartId, $ttlMinutes) {
            // Lock existing reservation for update to prevent race conditions
            $reservation = StockReservation::query()
                ->where('stockable_type', $stockable->getMorphClass())
                ->where('stockable_id', $stockable->getKey())
                ->where('cart_id', $cartId)
                ->lockForUpdate()
                ->first();

            // Calculate available stock (excluding own reservation if updating)
            $currentStock = $this->stockService->getCurrentStock($stockable);
            $otherReserved = StockReservation::query()
                ->where('stockable_type', $stockable->getMorphClass())
                ->where('stockable_id', $stockable->getKey())
                ->where('cart_id', '!=', $cartId)
                ->active()
                ->lockForUpdate()
                ->sum('quantity');

            $availableStock = max(0, $currentStock - (int) $otherReserved);

            if ($availableStock < $quantity) {
                return null;
            }

            if ($reservation) {
                $reservation->update([
                    'quantity' => $quantity,
                    'expires_at' => now()->addMinutes($ttlMinutes),
                ]);
            } else {
                $reservation = StockReservation::create([
                    'stockable_type' => $stockable->getMorphClass(),
                    'stockable_id' => $stockable->getKey(),
                    'cart_id' => $cartId,
                    'quantity' => $quantity,
                    'expires_at' => now()->addMinutes($ttlMinutes),
                ]);
            }

            if ($this->isEventEnabled('reserved')) {
                StockReserved::dispatch($stockable, $quantity, $cartId, $reservation);
            }

            return $reservation;
        });
    }

    /**
     * Release stock reservation for a cart.
     */
    public function release(Model $stockable, string $cartId): bool
    {
        $reservation = StockReservation::query()
            ->where('stockable_type', $stockable->getMorphClass())
            ->where('stockable_id', $stockable->getKey())
            ->where('cart_id', $cartId)
            ->first();

        if (! $reservation) {
            return false;
        }

        $quantity = $reservation->quantity;
        $reservation->delete();

        if ($this->isEventEnabled('released')) {
            StockReleased::dispatch($stockable, $quantity, $cartId);
        }

        return true;
    }

    /**
     * Release all reservations for a cart.
     */
    public function releaseAllForCart(string $cartId): int
    {
        $reservations = StockReservation::query()
            ->where('cart_id', $cartId)
            ->get();

        $count = 0;
        $dispatchEvents = $this->isEventEnabled('released');

        foreach ($reservations as $reservation) {
            $stockable = $reservation->stockable;
            $quantity = $reservation->quantity;
            $reservation->delete();
            $count++;

            if ($stockable && $dispatchEvents) {
                StockReleased::dispatch($stockable, $quantity, $cartId);
            }
        }

        return $count;
    }

    /**
     * Convert reservations to actual stock deductions.
     *
     * Called after successful payment to deduct stock.
     *
     * @return array<StockTransaction>
     */
    public function commitReservations(string $cartId, ?string $orderId = null): array
    {
        $reservations = StockReservation::query()
            ->where('cart_id', $cartId)
            ->with('stockable')
            ->get();

        $transactions = [];

        foreach ($reservations as $reservation) {
            $stockable = $reservation->stockable;

            if (! $stockable) {
                $reservation->delete();

                continue;
            }

            $transaction = $this->stockService->removeStock(
                model: $stockable,
                quantity: $reservation->quantity,
                reason: 'sale',
                note: $orderId ? "Order #{$orderId}" : "Cart {$cartId}"
            );

            $transactions[] = $transaction;

            if ($this->isEventEnabled('deducted')) {
                StockDeducted::dispatch(
                    $stockable,
                    $reservation->quantity,
                    'sale',
                    $orderId,
                    $transaction
                );
            }

            // Check for low stock or out of stock
            $this->checkStockLevels($stockable);

            $reservation->delete();
        }

        return $transactions;
    }

    /**
     * Deduct stock directly without using reservations.
     *
     * Useful for immediate purchase flows.
     */
    public function deductStock(
        Model $stockable,
        int $quantity,
        string $reason = 'sale',
        ?string $orderId = null
    ): StockTransaction {
        $transaction = $this->stockService->removeStock(
            model: $stockable,
            quantity: $quantity,
            reason: $reason,
            note: $orderId ? "Order #{$orderId}" : null
        );

        if ($this->isEventEnabled('deducted')) {
            StockDeducted::dispatch($stockable, $quantity, $reason, $orderId, $transaction);
        }

        // Check for low stock or out of stock
        $this->checkStockLevels($stockable);

        return $transaction;
    }

    /**
     * Get the available stock (current - reserved).
     */
    public function getAvailableStock(Model $stockable): int
    {
        $currentStock = $this->stockService->getCurrentStock($stockable);

        $reserved = StockReservation::query()
            ->where('stockable_type', $stockable->getMorphClass())
            ->where('stockable_id', $stockable->getKey())
            ->active()
            ->sum('quantity');

        return max(0, $currentStock - (int) $reserved);
    }

    /**
     * Check if stock is available (accounting for reservations).
     */
    public function hasAvailableStock(Model $stockable, int $quantity = 1): bool
    {
        return $this->getAvailableStock($stockable) >= $quantity;
    }

    /**
     * Get the reserved quantity for a stockable.
     */
    public function getReservedQuantity(Model $stockable): int
    {
        return (int) StockReservation::query()
            ->where('stockable_type', $stockable->getMorphClass())
            ->where('stockable_id', $stockable->getKey())
            ->active()
            ->sum('quantity');
    }

    /**
     * Get reservation for a specific cart and stockable.
     */
    public function getReservation(Model $stockable, string $cartId): ?StockReservation
    {
        return StockReservation::query()
            ->where('stockable_type', $stockable->getMorphClass())
            ->where('stockable_id', $stockable->getKey())
            ->where('cart_id', $cartId)
            ->first();
    }

    /**
     * Clean up expired reservations.
     *
     * Respects the `stock.cleanup.keep_expired_for_minutes` config
     * which allows keeping expired reservations for debugging.
     */
    public function cleanupExpired(): int
    {
        $keepForMinutes = (int) config('stock.cleanup.keep_expired_for_minutes', 0);

        $query = StockReservation::query();

        if ($keepForMinutes > 0) {
            // Only delete reservations expired longer than the grace period
            $query->where('expires_at', '<=', now()->subMinutes($keepForMinutes));
        } else {
            $query->expired();
        }

        return $query->delete();
    }

    /**
     * Extend a reservation.
     */
    public function extend(
        Model $stockable,
        string $cartId,
        ?int $minutes = null
    ): ?StockReservation {
        $minutes ??= config('stock.cart.reservation_ttl', 30);

        $reservation = $this->getReservation($stockable, $cartId);
        $reservation = $this->getReservation($stockable, $cartId);

        if (! $reservation) {
            return null;
        }

        $reservation->extend($minutes);

        return $reservation;
    }

    /**
     * Check stock levels and dispatch events if needed.
     */
    private function checkStockLevels(Model $stockable): void
    {
        $currentStock = $this->stockService->getCurrentStock($stockable);
        $threshold = (int) config('stock.low_stock_threshold', 10);

        if ($currentStock <= 0 && $this->isEventEnabled('out_of_stock')) {
            OutOfStock::dispatch($stockable);
        } elseif ($currentStock <= $threshold && $this->isEventEnabled('low_stock')) {
            LowStockDetected::dispatch($stockable, $currentStock, $threshold);
        }
    }
}
