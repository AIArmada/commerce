<?php

declare(strict_types=1);

namespace AIArmada\Stock\Cart;

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Stock\Services\StockReservationService;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;

/**
 * Extends CartManager with stock-aware functionality.
 *
 * When the stock package detects the cart package is installed,
 * it wraps the CartManager with stock reservation capabilities.
 */
final class CartManagerWithStock extends CartManager
{
    private ?StockReservationService $reservationService = null;

    public function __construct(
        StorageInterface $storage,
        ?Dispatcher $events = null,
        bool $eventsEnabled = true,
        ?CartConditionResolver $conditionResolver = null,
    ) {
        parent::__construct($storage, $events, $eventsEnabled, $conditionResolver);
    }

    /**
     * Create from existing CartManager.
     */
    public static function fromCartManager(CartManager $manager): self
    {
        $reflection = new \ReflectionClass($manager);

        // Extract constructor dependencies from the parent
        $storage = null;
        $events = null;
        $eventsEnabled = true;
        $conditionResolver = null;

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);

            if (! $property->isInitialized($manager)) {
                continue;
            }

            $value = $property->getValue($manager);

            match ($property->getName()) {
                'storage' => $storage = $value,
                'events' => $events = $value,
                'eventsEnabled' => $eventsEnabled = $value,
                'conditionResolver' => $conditionResolver = $value,
                default => null,
            };
        }

        if ($storage === null) {
            throw new \RuntimeException('Cannot create CartManagerWithStock: storage is required');
        }

        $instance = new self($storage, $events, $eventsEnabled, $conditionResolver);

        // Copy remaining state from existing manager
        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);

            if (! $property->isInitialized($manager)) {
                continue;
            }

            // Skip properties we already set via constructor
            if (in_array($property->getName(), ['storage', 'events', 'eventsEnabled', 'conditionResolver'], true)) {
                continue;
            }

            $property->setValue($instance, $property->getValue($manager));
        }

        return $instance;
    }

    /**
     * Set the reservation service.
     */
    public function setReservationService(StockReservationService $service): self
    {
        $this->reservationService = $service;

        return $this;
    }

    /**
     * Get the reservation service.
     */
    public function getReservationService(): StockReservationService
    {
        if ($this->reservationService === null) {
            $this->reservationService = app(StockReservationService::class);
        }

        return $this->reservationService;
    }

    /**
     * Reserve stock for all items in the current cart.
     *
     * Call this when entering checkout.
     *
     * @param  int  $ttlMinutes  Reservation expiry time
     * @return array<string, bool> Results per item ID
     */
    public function reserveAllStock(int $ttlMinutes = 30): array
    {
        $cart = $this->getCurrentCart();
        $results = [];

        foreach ($cart->getItems() as $item) {
            $model = $item->getAssociatedModel();

            if (! $model instanceof Model) {
                continue;
            }

            $reservation = $this->getReservationService()->reserve(
                $model,
                $item->quantity,
                $this->getCartIdentifier(),
                $ttlMinutes
            );

            $results[$item->id] = $reservation !== null;
        }

        return $results;
    }

    /**
     * Release all stock reservations for the current cart.
     *
     * Call this when abandoning checkout or clearing cart.
     */
    public function releaseAllStock(): int
    {
        return $this->getReservationService()->releaseAllForCart(
            $this->getCartIdentifier()
        );
    }

    /**
     * Commit all reservations (deduct stock after payment).
     *
     * @param  string|null  $orderId  Optional order reference
     * @return array<\AIArmada\Stock\Models\StockTransaction>
     */
    public function commitStock(?string $orderId = null): array
    {
        return $this->getReservationService()->commitReservations(
            $this->getCartIdentifier(),
            $orderId
        );
    }

    /**
     * Check if all items in cart have sufficient stock.
     *
     * @return array{available: bool, issues: array<string, array{name: string, requested: int, available: int}>}
     */
    public function validateStock(): array
    {
        $cart = $this->getCurrentCart();
        $issues = [];
        $allAvailable = true;

        foreach ($cart->getItems() as $item) {
            $model = $item->getAssociatedModel();

            if (! $model instanceof Model) {
                continue;
            }

            $availableStock = $this->getReservationService()->getAvailableStock($model);

            // Exclude own reservation from availability check
            $ownReservation = $this->getReservationService()->getReservation(
                $model,
                $this->getCartIdentifier()
            );

            if ($ownReservation) {
                $availableStock += $ownReservation->quantity;
            }

            if ($availableStock < $item->quantity) {
                $allAvailable = false;
                $issues[$item->id] = [
                    'name' => $item->name,
                    'requested' => $item->quantity,
                    'available' => $availableStock,
                ];
            }
        }

        return [
            'available' => $allAvailable,
            'issues' => $issues,
        ];
    }

    /**
     * Get the cart identifier for reservations.
     */
    private function getCartIdentifier(): string
    {
        $cart = $this->getCurrentCart();
        $cartId = $cart->getId();

        if ($cartId !== null) {
            return (string) $cartId;
        }

        return sprintf('%s_%s', $cart->getIdentifier(), $cart->instance());
    }
}
