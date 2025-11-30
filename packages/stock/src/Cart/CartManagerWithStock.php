<?php

declare(strict_types=1);

namespace AIArmada\Stock\Cart;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Stock\Services\StockReservationService;
use Illuminate\Database\Eloquent\Model;

/**
 * CartManager decorator that adds stock reservation functionality.
 *
 * Uses composition pattern to wrap any CartManagerInterface implementation,
 * enabling stacking with other decorators (e.g., CartManagerWithVouchers, CartManagerWithAffiliates).
 */
final class CartManagerWithStock implements CartManagerInterface
{
    private ?StockReservationService $reservationService = null;

    public function __construct(
        private CartManagerInterface $manager
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->manager->{$method}(...$arguments);
    }

    /**
     * Create from existing CartManagerInterface.
     */
    public static function fromCartManager(CartManagerInterface $manager): self
    {
        if ($manager instanceof self) {
            return $manager;
        }

        return new self($manager);
    }

    /**
     * Get the underlying CartManager (unwraps all decorators if needed)
     */
    public function getBaseManager(): CartManagerInterface
    {
        if ($this->manager instanceof self) {
            return $this->manager->getBaseManager();
        }

        return $this->manager;
    }

    public function getCurrentCart(): Cart
    {
        return $this->manager->getCurrentCart();
    }

    public function getCartInstance(string $name, ?string $identifier = null): Cart
    {
        return $this->manager->getCartInstance($name, $identifier);
    }

    public function instance(): string
    {
        return $this->manager->instance();
    }

    public function setInstance(string $name): static
    {
        $this->manager->setInstance($name);

        return $this;
    }

    public function setIdentifier(string $identifier): static
    {
        $this->manager->setIdentifier($identifier);

        return $this;
    }

    public function forgetIdentifier(): static
    {
        $this->manager->forgetIdentifier();

        return $this;
    }

    public function forTenant(string $tenantId): static
    {
        return new self($this->manager->forTenant($tenantId));
    }

    public function getTenantId(): ?string
    {
        return $this->manager->getTenantId();
    }

    public function session(?string $sessionKey = null): StorageInterface
    {
        return $this->manager->session($sessionKey);
    }

    public function getById(string $uuid): ?Cart
    {
        return $this->manager->getById($uuid);
    }

    public function swap(string $oldIdentifier, string $newIdentifier, string $instance = 'default'): bool
    {
        return $this->manager->swap($oldIdentifier, $newIdentifier, $instance);
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
